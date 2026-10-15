<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visa enquiry received</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#17143f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding:28px 16px">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border:1px solid #e5eaf1;border-radius:16px;overflow:hidden">
                    <tr>
                        <td style="background:#0c2350;padding:28px;color:#ffffff">
                            <strong style="font-size:24px;letter-spacing:2px">KAROSSY</strong>
                            <div style="margin-top:5px;font-size:11px;letter-spacing:1.5px;color:#d4dfef">TRAVELS &amp; TOURS LIMITED</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px">
                            <p style="margin:0 0 12px;color:#bf1e2e;font-size:12px;font-weight:bold;letter-spacing:1px">VISA ENQUIRY RECEIVED</p>
                            <h1 style="margin:0 0 20px;font-size:26px;line-height:1.3">Thank you for choosing Karossy.</h1>
                            <p style="font-size:15px;line-height:1.7">Hello {{ $enquiry->name }},</p>
                            <p style="font-size:15px;line-height:1.7;color:#5f6877">Thank you for your visa enquiry. We have received your request, and our Visa Team will review the information provided and contact you with the applicable requirements, fees and next steps.</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;background:#f4f7fb;border-radius:10px">
                                <tr>
                                    <td style="padding:18px 20px">
                                        <div style="font-size:12px;color:#65748b;margin-bottom:8px">YOUR ENQUIRY REFERENCE</div>
                                        <strong style="font-size:20px;color:#0c2350">{{ $enquiry->reference }}</strong>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size:14px;line-height:1.7;color:#5f6877">This email acknowledges receipt of your enquiry only; it is not a visa approval or confirmation that an application has been submitted.</p>
                            <p style="font-size:14px;line-height:1.7;color:#5f6877">If you need to update your enquiry, contact <a href="mailto:{{ config('travel.support.email') }}" style="color:#bf1e2e">{{ config('travel.support.email') }}</a> and quote the reference above.</p>
                            <p style="margin:24px 0 0;font-size:15px;line-height:1.7">Warm regards,<br><strong>The Karossy Visa Team</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 28px;background:#f4f7fb;font-size:12px;line-height:1.6;color:#65748b">Karossy Travels &amp; Tours Limited<br>Your Satisfaction, Our Peace.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
