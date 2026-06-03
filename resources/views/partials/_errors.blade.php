@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Periksa kembali isian:</strong>
        <ul class="mb-0 small">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif
