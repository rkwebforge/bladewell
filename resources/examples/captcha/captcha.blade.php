{{-- $captchaUrl is your captcha image's URL from the controller (for example captcha_src() with mews/captcha), and $captchaAudioUrl the same code spoken, so people who can't see the image can still get through. The refresh button shows another code. --}}
<x-widget.captcha label="Captcha" placeholder="Enter the text" :src="$captchaUrl" :audio-src="$captchaAudioUrl" class="max-w-sm" />
