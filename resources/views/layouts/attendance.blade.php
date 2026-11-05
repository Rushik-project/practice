<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Attendance</title>
    <style>
        :root{font-family:system-ui,-apple-system,sans-serif;color:#172d32;background:#f3f6f5}*{box-sizing:border-box}body{margin:0}header{background:#143d35;color:white;padding:22px 5%;display:flex;align-items:center;justify-content:space-between;gap:20px}header strong{font-size:21px;letter-spacing:-.5px}header small{display:block;color:#b6d2c6;margin-top:5px}main{max-width:1280px;margin:auto;padding:36px 24px}h1{font-size:32px;letter-spacing:-1px;margin:0 0 8px}h2{font-size:18px;margin:0 0 22px}p{color:#657873;margin:0 0 24px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin:28px 0}.card,.panel{background:white;border:1px solid #dfe7e3;border-radius:14px;padding:24px}.card span{display:block;color:#657873;font-size:13px}.card strong{display:block;font-size:32px;margin-top:10px}.layout{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:22px}.filters{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:22px}label{font-size:12px;font-weight:600;display:block;color:#50675e;margin-bottom:7px}input{font:inherit;min-width:0;width:100%;border:1px solid #ccd9d2;border-radius:8px;padding:10px 12px;background:white}button{cursor:pointer;border:0;border-radius:8px;padding:11px 16px;background:#1e6551;color:white;font:inherit;font-size:13px;font-weight:600}button:hover{background:#174b3d}header button{background:#ffffff20;border:1px solid #ffffff40}.field{margin-bottom:18px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;text-align:left;white-space:nowrap}th{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:#73827b;background:#f7f9f8}td,th{padding:16px 12px;border-bottom:1px solid #edf1ee}td{font-size:13px}td small{display:block;color:#75877e;margin-top:4px}.badge{border-radius:20px;padding:6px 10px;font-size:11px;background:#eef1ef;color:#64756c}.present{background:#def3e8;color:#1c6d4b}.complete{background:#e4ecfb;color:#355c96}.notice{padding:14px 18px;border-radius:8px;background:#dff1e6;margin-bottom:20px}.error{background:#ffe8e5;color:#9a3226}.empty{text-align:center;padding:50px 20px;color:#71827a}.hint{font-size:12px;line-height:1.7;margin-top:18px}.pagination{margin-top:20px}.pagination svg{width:20px}.pagination nav>div{margin:10px 0}a{color:#216c54}@media(max-width:950px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:550px){main{padding:24px 14px}.panel{padding:16px}header{padding:18px}header small{max-width:150px;overflow:hidden}h1{font-size:26px}.card{padding:18px}.filters>div{width:100%}}
    </style>
    @include('partials.theme-head')
</head>
<body>
<header>
    <div><strong>◷ &nbsp; Attendance</strong><small>Your people. Every day.</small></div>
    <div><small>{{ auth()->user()->name }}</small></div>
    <nav style="display:flex;gap:16px;flex-wrap:wrap">
        @if(auth()->user()->isAdmin())
            <a style="color:white" href="{{ route('admin.dashboard') }}">Admin dashboard</a>
            <a style="color:white" href="{{ route('employees.index') }}">Employee list</a>
            <a style="color:white" href="{{ route('employees.index') }}#add-employee">Add employee</a>
            <a style="color:white" href="{{ route('admin.leave') }}">Leave requests</a>
        @else
            <a style="color:white" href="{{ route('employee.dashboard') }}">My attendance</a>
            <a style="color:white" href="{{ route('employee.leave') }}">Apply for leave</a>
        @endif
        <a style="color:white" href="{{ route('holidays.index') }}">Holiday list</a>
    </nav>
    @include('partials.theme-toggle')
    <form method="POST" action="{{ route('logout') }}">@csrf<button>Sign out</button></form>
</header>
<main>
@yield('content')
</main>
</body>
</html>
