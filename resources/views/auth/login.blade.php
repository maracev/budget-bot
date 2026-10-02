@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div style="max-width:420px;margin:2rem auto;">
        <div class="card">
            <h2 style="margin-bottom:1rem;">Iniciar sesión</h2>
            @if ($errors->any())
                <div class="alert" style="background:#fff5f5;border:1px solid #fed7d7;color:#c53030;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div style="margin-bottom:1rem;">
                    <label for="email">Email</label>
                    <input type="text" id="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div style="margin-bottom:1rem;">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Ingresar</button>
            </form>
        </div>
    </div>
@endsection
