<x-mail::message>
# Join {{ $organizationName }}

{{ $inviterName }} invited you to join **{{ $organizationName }}** on Arkion as **{{ $roleLabel }}**.

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

Sign in with this email address to accept. This invitation expires on {{ $expiresAt->toFormattedDayDateString() }}.

If you weren't expecting this invitation, you can ignore this email.
</x-mail::message>
