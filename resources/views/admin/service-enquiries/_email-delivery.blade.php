<section class="card content-card p-4 mt-4" aria-labelledby="email-delivery-heading">
    <h2 class="h5" id="email-delivery-heading">Email delivery</h2>
    <dl class="row mb-2">
        @foreach(['customer' => 'Customer acknowledgement', 'team' => 'Team notification'] as $channel => $label)
            <dt class="col-sm-7">{{ $label }}</dt><dd class="col-sm-5">{{ str(data_get($enquiry->email_delivery, $channel.'.state', 'not recorded'))->headline() }}</dd>
        @endforeach
    </dl>
    <p class="small text-secondary">Accepted means accepted by the configured mail transport, not confirmed inbox delivery. Internal To, CC and BCC recipients are managed in General Settings.</p>
    @if(collect($enquiry->email_delivery ?? [])->contains(fn ($entry) => data_get($entry, 'state') === 'failed'))
        @if(auth()->user()->hasPermission('services.manage'))
            <form action="{{ $retryUrl }}" method="POST">@csrf<button class="btn btn-outline-dark" type="submit">Retry failed emails</button></form>
        @endif
    @endif
</section>
