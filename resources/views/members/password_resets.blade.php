<section id="password-resets" class="member-reset-section" aria-labelledby="password-resets-heading">
    <h2 id="password-resets-heading">Password reset requests <small>({{ $pendingResetCount }} pending)</small></h2>
    <p class="card-help">Verify the member's identity before approving a request. The five-digit code is shown once after approval; give it directly to the member.</p>
    @if(session('reset_code'))
        <div class="notice success" role="status"><strong>Code for {{ session('reset_username') }}: {{ session('reset_code') }}</strong><br>Give this code directly to the verified member. It expires in 15 minutes and can be used once on the password reset form.</div>
    @endif
    @forelse($resetRequests as $appeal)
        <article class="appeal-card">
            <div class="appeal-meta"><div><strong>{{ '@'.$appeal->username }}</strong><small>Submitted {{ $appeal->created_at->format('M j, Y · g:i A') }}</small></div><span class="status-pill {{ $appeal->status === 'pending' ? 'upcoming' : (in_array($appeal->status, ['approved', 'completed']) ? 'active' : 'expired') }}">{{ ucfirst($appeal->status) }}</span></div>
            <p class="appeal-message">{{ $appeal->message }}</p>
            @if($appeal->status === 'pending')
                <form method="POST" action="{{ route('admin.appeals.update', $appeal) }}">@csrf @method('PATCH')
                    <label for="note-{{ $appeal->id }}">Admin note (optional)</label><textarea id="note-{{ $appeal->id }}" name="admin_note" rows="3" maxlength="2000"></textarea>
                    <div class="row-actions">@if($appeal->user && $appeal->user->role === 'member' && $appeal->user->approval_status === 'approved' && ! $appeal->user->trashed())<button class="approve-button" type="submit" name="status" value="approved">Approve and generate code</button>@endif<button class="secondary-button" type="submit" name="status" value="declined">Decline</button></div>
                </form>
            @elseif($appeal->admin_note)<p class="appeal-note"><strong>Admin note:</strong> {{ $appeal->admin_note }}</p>@endif
        </article>
    @empty <p class="empty-message">No password reset requests yet.</p> @endforelse
    <div class="pagination">{{ $resetRequests->links() }}</div>
</section>
