 <!--! [Start] Header !-->
 <!--! ================================================================ !-->
 <header class="nxl-header">
     <div class="header-wrapper">
         <!--! [Start] Header Left !-->
         <div class="header-left d-flex align-items-center gap-4">
             <!--! [Start] nxl-head-mobile-toggler !-->
             <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                 <div class="hamburger hamburger--arrowturn">
                     <div class="hamburger-box">
                         <div class="hamburger-inner"></div>
                     </div>
                 </div>
             </a>
             <!--! [Start] nxl-head-mobile-toggler !-->
             <!--! [Start] nxl-navigation-toggle !-->
             <div class="nxl-navigation-toggle">
                 <a href="javascript:void(0);" id="menu-mini-button">
                     <i class="feather-align-left"></i>
                 </a>
                 <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                     <i class="feather-arrow-right"></i>
                 </a>
             </div>
             <!--! [End] nxl-navigation-toggle !-->
             <!--! [Start] nxl-lavel-mega-menu-toggle !-->
             <!--<div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">-->
             <!--    <a href="javascript:void(0);" id="nxl-lavel-mega-menu-open">-->
             <!--        <i class="feather-align-left"></i>-->
             <!--    </a>-->
             <!--</div>-->
             <!--! [End] nxl-lavel-mega-menu-toggle !-->
             <!--! [Start] nxl-lavel-mega-menu !-->
             <div class="nxl-drp-link nxl-lavel-mega-menu">
                 <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                     <a href="javascript:void(0)" id="nxl-lavel-mega-menu-hide">
                         <i class="feather-arrow-left me-2"></i>
                         <span>Back</span>
                     </a>
                 </div>

             </div>
             <!--! [End] nxl-lavel-mega-menu !-->
         </div>
         <!--! [End] Header Left !-->
         <!--! [Start] Header Right !-->
         <div class="header-right ms-auto">
             <div class="d-flex align-items-center">

                 <div class="nxl-h-item d-none d-sm-flex">
                     <div class="full-screen-switcher">
                         <a href="javascript:void(0);" class="nxl-head-link me-0"
                             onclick="$('body').fullScreenHelper('toggle');">
                             <i class="feather-maximize maximize"></i>
                             <i class="feather-minimize minimize"></i>
                         </a>
                     </div>
                 </div>
                 {{-- A page can put its own icon just before the bell (admin dashboard: "Needs your action"). --}}
                 @stack('header-before-bell')
                 @include('client.layout.partials.notification-bell')

                 {{-- The dark/light theme switch was removed (2026-10-04): reset anyone who had picked
                      dark so they aren't stuck in it. Runs before the theme script reads this key. --}}
                 <script>
                     try { localStorage.setItem('app-skin-dark', 'app-skin-light'); localStorage.removeItem('app-skin'); } catch (e) {}
                     document.documentElement.classList.remove('app-skin-dark');
                 </script>

                 {{-- Profile: hover (or click/tap) shows name, email, employee ID, My Profile + Logout --}}
                 @php
                     $me = auth()->user();
                     $meBasic = $me?->basicDetails;
                     $mePhoto = $meBasic?->profile_image ? file_url($meBasic->profile_image, 'profile_photo') : null;
                     $meInitials = strtoupper(collect(preg_split('/\s+/', trim($me->name ?? 'U')))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
                 @endphp
                 <div class="dropdown nxl-h-item profile-hover" id="profileMenu">
                     <a href="javascript:void(0);" class="profile-trigger" data-bs-toggle="dropdown" role="button"
                         aria-expanded="false" aria-label="Profile menu">
                         @if ($mePhoto)
                             <img src="{{ $mePhoto }}" alt="{{ $me->name }}" class="user-avtar me-0">
                         @else
                             <span class="user-avtar user-initials me-0">{{ $meInitials }}</span>
                         @endif
                     </a>
                     <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown profile-card">
                         <div class="profile-card-head">
                             @if ($mePhoto)
                                 <img src="{{ $mePhoto }}" alt="{{ $me->name }}" class="profile-card-avatar">
                             @else
                                 <span class="profile-card-avatar user-initials">{{ $meInitials }}</span>
                             @endif
                             <div class="min-w-0">
                                 <div class="profile-card-name">{{ $me->name }}</div>
                                 <div class="profile-card-sub" title="{{ $me->email }}">{{ $me->email }}</div>
                                 <div class="profile-card-sub">Employee ID: <strong>{{ $me->employee_id ?: '—' }}</strong></div>
                                 <span class="profile-card-role">{{ $me->role === 'hr' ? 'HR' : ucfirst($me->role) }}</span>
                             </div>
                         </div>
                         <div class="dropdown-divider my-0"></div>
                         @if (in_array($me->role, ['employee', 'manager', 'hr'], true))
                             <a href="{{ route('my-profile.show') }}" class="dropdown-item">
                                 <i class="feather-user me-2"></i> My Profile
                             </a>
                         @endif
                         <a href="{{ route('logout') }}" class="dropdown-item text-danger">
                             <i class="feather-log-out me-2"></i> Logout
                         </a>
                     </div>
                 </div>
             </div>
         </div>
         <!--! [End] Header Right !-->
     </div>
 </header>
 <!--! ================================================================ !-->
 <!--! [End] Header !-->
 <!--! ================================================================ !-->
