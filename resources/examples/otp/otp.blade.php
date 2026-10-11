{{-- One box per digit. Typing moves to the next box, Backspace to the previous, and a pasted code fills them all. Once every box is filled it fires otp:complete, for a form that submits itself. --}}
<x-widget.otp name="otp" label="One-time password" />
