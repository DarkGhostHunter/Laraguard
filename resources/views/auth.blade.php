<!doctype html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
          integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    <title>Two Factor Authentication</title>
    <style>
        #box-container {
            min-height: 100vh;
        }
        #auth-form {
            margin-bottom: 6rem;
        }
        .cool-shadow {
            box-shadow: 0 2.8px 2.2px rgba(0, 0, 0, 0.1),
            0 6.7px 5.3px rgba(0, 0, 0, 0.072),
            0 12.5px 10px rgba(0, 0, 0, 0.06),
            0 22.3px 17.9px rgba(0, 0, 0, 0.05),
            0 41.8px 33.4px rgba(0, 0, 0, 0.04),
            0 100px 80px rgba(0, 0, 0, 0.028);
        }
    </style>
</head>
<body class="bg-light">
<div class="container">
    <div id="box-container" class="row justify-content-center align-items-center">
        <div id="form-container" class="col-lg-6 col-md-8 col-sm-10 col-12">
            <form id="auth-form" action="{{ $action }}" method="post" class="card border-0 cool-shadow">
                @csrf
                @foreach($credentials as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                @if($remember)
                    <input type="hidden" name="remember" value="on">
                @endif
                <section class="card-body">
                    <h2 class="card-title h5 text-center ">{{ __('Two Factor Authentication') }}</h2>
                    <hr>
                    <p class="text-center">
                        {{ __('To log in, open up your Authenticator app and issue the 6-digit code.') }}
                    </p>
                    <div class="form-row justify-content-center py-3">
                        <div class="col-sm-8 col-8 mb-3">
                            <input type="text" name="2fa_code" id="2fa_code"
                                   class="@if($error) is-invalid @endif form-control form-control-lg"
                                   minlength="6" placeholder="123456" required>
                            @if($error)
                                <div class="invalid-feedback">
                                    {{ __('The Code is invalid or has expired.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-auto mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            {{ __('Log in') }}
                        </button>
                    </div>
                </section>
            </form>
            <div class="text-black-50 small text-center">
                <a href="javascript:history.back()" class="btn btn-sm text-secondary btn-link">
                    &laquo; Go back
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
