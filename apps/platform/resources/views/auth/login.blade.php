@extends('layouts.app')

@section('content')
    <section class="panel page-header">
        <h1>Log in</h1>
        <p>Use the admin user created during local setup.</p>
    </section>

    <section class="panel">
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>

            <label>
                <input name="remember" type="checkbox" value="1" style="width: auto;">
                Remember me
            </label>

            <button type="submit">Log in</button>
        </form>
    </section>
@endsection
