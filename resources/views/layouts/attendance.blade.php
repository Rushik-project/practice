<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Attendance</title>
    <style>
        :root{font-family:system-ui,-apple-system,sans-serif;color:#172d32;background:#f3f6f5}*{box-sizing:border-box}body{margin:0}header{background:#143d35;color:white;padding:22px 5%;display:flex;align-items:center;justify-content:space-between;gap:20px}header strong{font-size:21px;letter-spacing:-.5px}header small{display:block;color:#b6d2c6;margin-top:5px}main{max-width:1280px;margin:auto;padding:36px 24px}h1{font-size:32px;letter-spacing:-1px;margin:0 0 8px}h2{font-size:18px;margin:0 0 22px}p{color:#657873;margin:0 0 24px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin:28px 0}.card,.panel{background:white;border:1px solid #dfe7e3;border-radius:14px;padding:24px}.card span{display:block;color:#657873;font-size:13px}.card strong{display:block;font-size:32px;margin-top:10px}.layout{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:22px}.filters{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:22px}label{font-size:12px;font-weight:600;display:block;color:#50675e;margin-bottom:7px}input{font:inherit;min-width:0;width:100%;border:1px solid #ccd9d2;border-radius:8px;padding:10px 12px;background:white}button{cursor:pointer;border:0;border-radius:8px;padding:11px 16px;background:#1e6551;color:white;font:inherit;font-size:13px;font-weight:600}button:hover{background:#174b3d}header button{background:#ffffff20;border:1px solid #ffffff40}.field{margin-bottom:18px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;text-align:left;white-space:nowrap}th{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:#73827b;background:#f7f9f8}td,th{padding:16px 12px;border-bottom:1px solid #edf1ee}td{font-size:13px}td small{display:block;color:#75877e;margin-top:4px}.badge{border-radius:20px;padding:6px 10px;font-size:11px;background:#eef1ef;color:#64756c}.present{background:#def3e8;color:#1c6d4b}.complete{background:#e4ecfb;color:#355c96}.notice{padding:14px 18px;border-radius:8px;background:#dff1e6;margin-bottom:20px}.error{background:#ffe8e5;color:#9a3226}.empty{text-align:center;padding:50px 20px;color:#71827a}.hint{font-size:12px;line-height:1.7;margin-top:18px}.pagination{margin-top:20px}.pagination svg{width:20px}.pagination nav>div{margin:10px 0}.settings-menu{position:relative}.settings-menu summary{cursor:pointer;list-style:none;padding:10px 14px;border:1px solid #ffffff40;border-radius:8px;font-size:13px;font-weight:600}.settings-menu summary::-webkit-details-marker{display:none}.settings-dropdown{position:absolute;z-index:10;right:0;top:calc(100% + 8px);min-width:170px;padding:8px;background:white;border:1px solid #dfe7e3;border-radius:10px;box-shadow:0 8px 24px #0002}.settings-dropdown a,.settings-dropdown button{display:block;width:100%;padding:10px 12px;border:0;border-radius:6px;background:transparent;color:#172d32;text-align:left;text-decoration:none;font:inherit;font-size:13px}.settings-dropdown a:hover,.settings-dropdown button:hover{background:#edf4f0;color:#172d32}.profile-details{display:grid;grid-template-columns:minmax(120px,180px) 1fr;gap:16px;margin:0}.profile-details dt{font-weight:600;color:#50675e}.profile-details dd{margin:0;overflow-wrap:anywhere}.profile-photo-section{display:flex;align-items:center;gap:22px;flex-wrap:wrap;margin-bottom:26px}.profile-photo{width:128px;height:128px;object-fit:cover;border-radius:50%;border:3px solid #dfe7e3}.profile-photo-placeholder{display:grid;place-items:center;width:128px;height:128px;border-radius:50%;background:#def3e8;color:#1c6d4b;font-size:48px;font-weight:700}.profile-photo-section form{width:min(100%,360px)}.profile-photo-section .hint{display:block;margin:8px 0 0}.profile-details{margin-top:22px}html[data-theme="dark"] .profile-photo{border-color:#53666c}html[data-theme="dark"] .profile-photo-placeholder{background:#193e32;color:#b3efd0}@media(max-width:950px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:550px){main{padding:24px 14px}.panel{padding:16px}header{padding:18px}header small{max-width:150px;overflow:hidden}h1{font-size:26px}.card{padding:18px}.filters>div{width:100%}.profile-details{grid-template-columns:1fr;gap:6px}.profile-details dd{margin-bottom:10px}}
    </style>
    @include('partials.theme-head')
</head>
<body>
<header>
    <div><strong>◷ &nbsp; Attendance</strong><small>Your people. Every day.</small></div>
    <nav class="dashboard-nav" aria-label="Main navigation">
        @if(auth()->user()->isAdmin())
            <a class="nav-button" href="{{ route('admin.dashboard') }}">Admin dashboard</a>
            <a class="nav-button" href="{{ route('employees.index') }}">Employee list</a>
            <a class="nav-button" href="{{ route('employees.index') }}#add-employee">Add employee</a>
            <a class="nav-button" href="{{ route('admin.attendance-summary') }}">Monthly attendance</a>
            <a class="nav-button" href="{{ route('admin.leave') }}">Leave requests</a>
        @else
            <a class="nav-button" href="{{ route('employee.dashboard') }}">My attendance</a>
            <a class="nav-button" href="{{ route('employee.colleagues') }}">Colleagues</a>
            <a class="nav-button" href="{{ route('employee.attendance-summary') }}">Monthly summary</a>
            <a class="nav-button" href="{{ route('employee.leave') }}">Apply for leave</a>
        @endif
        <a class="nav-button" href="{{ route('holidays.index') }}">Holiday list</a>
    </nav>
    @include('partials.theme-toggle')
    <details class="settings-menu">
        <summary>Settings</summary>
        <div class="settings-dropdown">
            <a href="{{ route('profile') }}">Profile</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
        </div>
    </details>
</header>
<main>
@yield('content')
</main>
</body>
</html>
