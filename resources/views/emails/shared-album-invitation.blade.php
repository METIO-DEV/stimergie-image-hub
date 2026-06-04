<p>Un album photo Stimergie a été partagé avec vous.</p>

<p><strong>{{ $album->name }}</strong></p>

@if ($album->description)
    <p>{{ $album->description }}</p>
@endif

@if ($message)
    <p>{{ $message }}</p>
@endif

<p>
    Accéder à l'album :
    <a href="{{ $shareUrl }}">{{ $shareUrl }}</a>
</p>

@if ($album->expires_at)
    <p>Ce lien est valable jusqu'au {{ $album->expires_at->translatedFormat('d F Y') }}.</p>
@endif
