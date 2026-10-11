{{-- A single field for a code: digits only (or letters too, with alphanumeric), and no longer than its length. A pasted "123-456" keeps just the digits. Once it holds a whole code it fires verification-code:complete, for a form that submits itself. --}}
<x-widget.verification-code name="code" label="Verification code" info="We sent a 6-digit code to ••• 4821." class="max-w-sm" />
