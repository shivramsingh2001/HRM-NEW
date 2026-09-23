<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="" />
    <meta name="keyword" content="" />
    <meta name="author" content="flexilecode" />
    <!--! BEGIN: Apps Title-->
    <title>HRM || Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!--! END:  Apps Title-->
    @include('client.layout.head')
    @yield('style')

</head>

<body>
    @includeWhen(session()->has('impersonation'), 'client.layout.impersonation-banner')
    @includeWhen(app()->bound('current_tenant'), 'client.layout.subscription-banner')
    @include('client.layout.sidebar')
    <!--! ================================================================ !-->
    <!--! [Start] Header !-->
    <!--! ================================================================ !-->
    @include('client.layout.header')
    <!--! ================================================================ !-->
    <!--! [End] Header !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="nxl-container apps-container apps-notes">
        <div class="nxl-content without-header nxl-full-content">
            <div class="main-content d-flex">
                <div class="content-area" data-scrollbar-target="#psScrollbarInit">
                    <div class="content-area-inner">
                        @yield('content-area')
                    </div>
                    @include('client.layout.footer')
                </div>
            </div>
        </div>
    </main>
    @yield('create-modal')
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    @include('client.layout.foot')
    @yield('script-area')
</body>

</html>
