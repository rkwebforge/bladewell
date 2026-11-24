// The field submits one international number (+919876543210), but the country picker only shapes it in the browser: check it in your Form Request. How long a number is, and what it starts with, depends on the country, and often on whether it's a mobile, so a length rule of your own lets wrong numbers through. propaganistas/laravel-phone checks each against Google's libphonenumber data.
public function rules(): array
{
    return [
        // Any country: the + and country code say which one to check against.
        'phone' => ['required', 'phone:INTERNATIONAL'],

        // Only mobiles, before you text a code to it.
        'mobile' => ['required', 'phone:INTERNATIONAL,mobile'],

        // Only the countries you serve.
        'office' => ['nullable', 'phone:IN,GB,MY'],

        // Without the package, the shape alone: a + and 7 to 15 digits, whatever the country.
        'fax' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
    ];
}

// A valid number still isn't proof it's theirs. Text a code to it, ask for it back in a verification code field, and
// only then mark the number verified. Each text costs money: throttle sending per number and per IP address.
