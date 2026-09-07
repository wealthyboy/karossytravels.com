<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment receipt — {{ $order->reference }}</title>
<style>
  body { margin:0; padding:0; background:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; color:#18181b; }
  .wrapper { max-width:600px; margin:32px auto; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:#15133f; padding:30px 40px; color:#fff; }
  .header h1 { margin:0; font-size:22px; }
  .header p { margin:6px 0 0; color:rgba(255,255,255,.75); font-size:14px; }
  .body { padding:34px 40px; }
  .amount { background:#f8fafc; border:1px solid #e4e4e7; border-radius:8px; padding:22px; margin:22px 0; }
  .amount small { display:block; color:#71717a; margin-bottom:6px; text-transform:uppercase; letter-spacing:.6px; }
  .amount strong { font-size:28px; color:#15133f; }
  table { width:100%; border-collapse:collapse; }
  td { padding:12px 0; border-bottom:1px solid #eee; font-size:14px; }
  td:last-child { text-align:right; font-weight:600; }
  .footer { padding:22px 40px; background:#f4f4f5; text-align:center; font-size:12px; color:#8a8a93; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Payment received</h1>
    <p>Karossy Travels &amp; Tours Limited</p>
  </div>
  <div class="body">
    <p>Your payment has been recorded successfully. Keep this receipt together with your Karossy booking reference.</p>
    <div class="amount">
      <small>Amount paid</small>
      <strong>{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</strong>
    </div>
    <table>
      <tr><td>Karossy reference</td><td>{{ $order->reference }}</td></tr>
      <tr><td>Payment reference</td><td>{{ $payment->gateway_reference }}</td></tr>
      <tr><td>Payment method</td><td>{{ str($payment->gateway)->headline() }}</td></tr>
      <tr><td>Status</td><td>{{ str($payment->status)->headline() }}</td></tr>
      <tr><td>Paid on</td><td>{{ $payment->paid_at?->format('D, d M Y H:i') }}</td></tr>
    </table>
    <p style="margin-top:24px;color:#52525b;font-size:14px;line-height:1.6;">This receipt confirms payment only. Flight ticket issuance and supplier confirmation are shown separately in your booking status.</p>
  </div>
  <div class="footer">Questions? {{ config('travel.support.email') }}</div>
</div>
</body>
</html>
