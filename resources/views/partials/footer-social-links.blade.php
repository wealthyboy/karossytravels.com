@if(count($footerSocialLinks))
    <nav class="public-footer-social" aria-label="Karossy social channels">
        @foreach($footerSocialLinks as $social)
            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Karossy on {{ $social['label'] }}" title="{{ $social['label'] }}"><i class="bi {{ $social['icon'] }}" aria-hidden="true"></i></a>
        @endforeach
    </nav>
@endif
