<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Laravel 13 Lab M3</title>
    <style>
        body { font-family: sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
        .err { color: red; }
        label { display: block; margin-bottom: .75rem; }
    </style>
</head>
<body>
<h1>Login</h1>

@if ($errors->any())
    <ul class="err">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf
    <label>Email:
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
    </label>
    <label>Password:
        <input type="password" name="password" required>
    </label>
    <label>
        <input type="checkbox" name="remember" value="1"> Ingat saya
    </label>
    <button type="submit">Masuk</button>
</form>

<p>Demo: <code>test@example.com</code> (password dari UserFactory)</p>
</body>
</html>
