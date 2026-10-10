<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Services\AutomaticCheckout;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    public function index(Request $request): View
    {
        $this->requireAdmin($request);
        app(AutomaticCheckout::class)->closeOverdue();
        $filters = $request->validate([
            'type' => ['nullable', 'in:all,qr,walk-in'],
            'period' => ['nullable', 'in:today,custom,all'],
            'date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
        ]);
        $type = $filters['type'] ?? 'all';
        $period = $filters['period'] ?? 'today';
        $date = match ($period) {
            'today' => today()->toDateString(),
            'custom' => $filters['date'],
            default => null,
        };
        return view('attendance.index', [
            'records' => Attendance::with('user')
                ->when($type !== 'all', fn ($query) => $query->where('method', $type))
                ->when($date, fn ($query) => $query->whereDate('check_in_date', $date))
                ->latest('checked_in_at')->paginate(20)->withQueryString(),
            'todayCount' => Attendance::whereDate('check_in_date', today())->count(),
            'selectedType' => $type,
            'selectedPeriod' => $period,
            'selectedDate' => $filters['date'] ?? '',
        ]);
    }

    public function scanner(Request $request): View
    {
        $this->requireAdmin($request);
        return view('attendance.scanner', [
            'todayCount' => Attendance::whereDate('check_in_date', today())->count(),
        ]);
    }

    public function qr(Request $request): Response
    {
        abort_unless($request->user()->role === 'member' && $request->user()->approval_status === 'approved', 403);
        $user = $request->user();
        if (! $user->qr_token) {
            $user->forceFill(['qr_token' => Str::random(48)])->save();
        }
        $svg = (new SvgWriter())->write(QrCode::create('DG:'.$user->qr_token)->setSize(260)->setMargin(10))->getString();
        return response($svg, 200)->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'private, no-store');
    }

    public function downloadId(Request $request): Response
    {
        abort_unless($request->user()->role === 'member' && $request->user()->approval_status === 'approved', 403);
        $user = $request->user();
        if (! $user->qr_token) {
            $user->forceFill(['qr_token' => Str::random(48)])->save();
        }

        $card = imagecreatetruecolor(720, 1080);
        $white = imagecolorallocate($card, 255, 253, 249);
        $brown = imagecolorallocate($card, 49, 32, 22);
        $orange = imagecolorallocate($card, 233, 128, 50);
        $muted = imagecolorallocate($card, 101, 75, 58);
        imagefilledrectangle($card, 0, 0, 719, 1079, $white);
        imagefilledrectangle($card, 0, 0, 719, 220, $brown);
        imagefilledrectangle($card, 0, 214, 719, 220, $orange);

        $logo = imagecreatefrompng(public_path('images/dfitness-logo.png'));
        imagecopyresampled($card, $logo, 52, 42, 0, 0, 136, 136, imagesx($logo), imagesy($logo));
        imagedestroy($logo);

        $font = 'C:/Windows/Fonts/arial.ttf';
        $boldFont = 'C:/Windows/Fonts/arialbd.ttf';
        if (is_file($font) && is_file($boldFont)) {
            imagettftext($card, 34, 0, 220, 100, $white, $boldFont, "D'FITNESS GYM");
            imagettftext($card, 22, 0, 222, 145, $white, $font, 'MEMBER ID');
            imagettftext($card, 19, 0, 55, 292, $muted, $boldFont, 'MEMBER NAME');
            $name = $user->name;
            $size = 38;
            while ($size > 18 && (imagettfbbox($size, 0, $boldFont, $name)[2] ?? 0) > 610) {
                $size -= 2;
            }
            imagettftext($card, $size, 0, 55, 352, $brown, $boldFont, $name);
            imagettftext($card, 18, 0, 166, 965, $muted, $font, 'Present this code to the gym admin');
            imagettftext($card, 18, 0, 208, 995, $muted, $font, 'for check-in and check-out.');
        } else {
            imagestring($card, 5, 220, 85, "D'FITNESS GYM", $white);
            imagestring($card, 4, 220, 120, 'MEMBER ID', $white);
            imagestring($card, 5, 55, 300, $user->name, $brown);
        }

        $qr = imagecreatefromstring((new PngWriter())->write(QrCode::create('DG:'.$user->qr_token)->setSize(430)->setMargin(12))->getString());
        imagecopy($card, $qr, 145, 420, 0, 0, imagesx($qr), imagesy($qr));
        imagedestroy($qr);
        imagefilledrectangle($card, 0, 1040, 719, 1079, $brown);
        ob_start();
        imagepng($card);
        $png = ob_get_clean();
        imagedestroy($card);

        return response($png, 200)->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="dfitness-member-id.png"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function checkIn(Request $request): RedirectResponse|JsonResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate(['qr_token' => ['required', 'string', 'max:67']]);
        $token = Str::startsWith($data['qr_token'], 'DG:') ? Str::after($data['qr_token'], 'DG:') : $data['qr_token'];
        [$record, $action] = DB::transaction(function () use ($token) {
            $member = User::where('qr_token', $token)->where('role', 'member')
                ->where('approval_status', 'approved')->lockForUpdate()->first();
            if (! $member) throw ValidationException::withMessages(['qr_token' => 'Member QR code was not found.']);

            $scanTime = now();
            $openVisit = Attendance::where('user_id', $member->id)->whereNull('checked_out_at')
                ->latest('checked_in_at')->lockForUpdate()->first();
            if ($openVisit) {
                if (app(AutomaticCheckout::class)->closeVisitIfOverdue($openVisit, $scanTime)) {
                    return [$openVisit->load('user'), 'check-out'];
                }
                $openVisit->update(['checked_out_at' => $scanTime]);
                return [$openVisit->load('user'), 'check-out'];
            }

            $hasMembership = Membership::where('user_id', $member->id)
                ->whereDate('starts_at', '<=', $scanTime->toDateString())
                ->whereDate('expires_at', '>=', $scanTime->toDateString())->exists();
            if (! $hasMembership) {
                $latestMembership = Membership::where('user_id', $member->id)->orderByDesc('expires_at')->first();
                $reason = match (true) {
                    $latestMembership === null => 'This member has no membership subscription yet.',
                    $latestMembership->expires_at->isBefore(today()) => 'This member’s membership expired on '.$latestMembership->expires_at->format('M j, Y').'.',
                    default => 'This member’s membership has not started yet.',
                };
                throw ValidationException::withMessages(['qr_token' => $reason]);
            }

            $record = Attendance::create([
                'user_id' => $member->id, 'method' => 'qr',
                'check_in_date' => $scanTime->toDateString(), 'checked_in_at' => $scanTime,
            ]);
            return [$record->load('user'), 'check-in'];
        });

        $message = $record->user->name.' '.$action.($record->auto_checked_out ? ' automatically at 10:00 PM.' : ' recorded.');
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'action' => $action,
                'record' => [
                    'id' => $record->id,
                    'name' => $record->user->name,
                    'username' => $record->user->username,
                    'checked_in_at' => $record->checked_in_at->format('M j, Y · g:i A'),
                    'checked_out_at' => $record->checked_out_at?->format('M j, Y · g:i A'),
                    'auto_checked_out' => $record->auto_checked_out,
                ],
                'today_count' => Attendance::whereDate('check_in_date', today())->count(),
            ]);
        }
        return back()->with('status', $message);
    }

    public function walkIn(Request $request): RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate(['walk_in_name' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($data) {
            $attendance = Attendance::create([
                'walk_in_name' => $data['walk_in_name'], 'method' => 'walk-in',
                'check_in_date' => today(), 'checked_in_at' => now(),
            ]);
            Payment::create([
                'attendance_id' => $attendance->id,
                'amount' => '120.00',
                'status' => 'paid',
                'paid_at' => today(),
                'method' => 'automatic',
            ]);
        });
        return redirect()->route('admin.attendance.index')->with('status', 'Walk-in recorded. ₱120.00 payment recorded automatically.');
    }
}
