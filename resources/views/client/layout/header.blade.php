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

                 {{-- Profile: hover (or click/tap) shows name, email, employee ID, My Profile, Change Password + Logout --}}
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
                         <a href="javascript:void(0);" class="dropdown-item" onclick="openChangePassword()">
                             <i class="feather-lock me-2"></i> Change Password
                         </a>
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

 {{-- Change Password modal (header profile menu). Posts to my-profile.password, same rules as the My Profile form. --}}
 <x-ui.modal id="changePasswordModal" title="Change Password" size="md" bodyOnly>
     <form id="changePasswordForm" autocomplete="off" novalidate>
         <div id="cpSuccess" class="alert alert-success d-none" style="font-size:12px;"><i class="feather-check-circle me-1"></i> Password changed successfully.</div>
         <div id="cpError" class="alert alert-danger d-none" style="font-size:12px;"></div>
         <div class="mb-3">
             <label class="fw-semibold" style="font-size:11.5px;" for="cpCurrent">Current Password <span class="text-danger">*</span></label>
             <div class="input-group">
                 <input type="password" class="form-control" id="cpCurrent" name="current_password" placeholder="Enter your current password" autocomplete="current-password" required>
                 <button type="button" class="btn btn-light border cp-eye" data-target="cpCurrent" aria-label="Show password"><i class="feather-eye"></i></button>
             </div>
             <small class="text-danger cp-err" data-for="current_password"></small>
         </div>
         <div class="mb-3">
             <label class="fw-semibold" style="font-size:11.5px;" for="cpNew">New Password <span class="text-danger">*</span></label>
             <div class="input-group">
                 <input type="password" class="form-control" id="cpNew" name="new_password" placeholder="Enter a new password" autocomplete="new-password" required>
                 <button type="button" class="btn btn-light border cp-eye" data-target="cpNew" aria-label="Show password"><i class="feather-eye"></i></button>
             </div>
             <small class="text-muted d-block" style="font-size:10.5px;">At least 8 characters, with upper- and lower-case letters and a number.</small>
             <small class="text-danger cp-err" data-for="new_password"></small>
         </div>
         <div class="mb-1">
             <label class="fw-semibold" style="font-size:11.5px;" for="cpConfirm">Confirm New Password <span class="text-danger">*</span></label>
             <div class="input-group">
                 <input type="password" class="form-control" id="cpConfirm" name="confirm_password" placeholder="Re-enter the new password" autocomplete="new-password" required>
                 <button type="button" class="btn btn-light border cp-eye" data-target="cpConfirm" aria-label="Show password"><i class="feather-eye"></i></button>
             </div>
             <small class="text-danger cp-err" data-for="confirm_password"></small>
         </div>
     </form>
     <x-slot:footer>
         <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Cancel</button>
         <button type="submit" form="changePasswordForm" class="btn btn-primary btn-sm" id="cpSaveBtn"><i class="feather-check me-1"></i> Update Password</button>
     </x-slot:footer>
 </x-ui.modal>

 <script>
     (function () {
         const modalEl = document.getElementById('changePasswordModal');
         const form = document.getElementById('changePasswordForm');
         const okBox = document.getElementById('cpSuccess');
         const errBox = document.getElementById('cpError');
         const btn = document.getElementById('cpSaveBtn');
         // The header is sticky; a modal inside it could sit under the backdrop, so it lives on <body>.
         document.body.appendChild(modalEl);

         function clearMessages() {
             okBox.classList.add('d-none');
             errBox.classList.add('d-none');
             errBox.textContent = '';
             form.querySelectorAll('.cp-err').forEach(el => { el.textContent = ''; });
             form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
         }

         window.openChangePassword = function () {
             form.reset();
             clearMessages();
             btn.disabled = false;
             bootstrap.Modal.getOrCreateInstance(modalEl).show();
         };

         form.querySelectorAll('.cp-eye').forEach(eye => eye.addEventListener('click', () => {
             const input = document.getElementById(eye.dataset.target);
             const show = input.type === 'password';
             input.type = show ? 'text' : 'password';
             eye.querySelector('i').className = show ? 'feather-eye-off' : 'feather-eye';
         }));

         form.addEventListener('submit', function (e) {
             e.preventDefault();
             clearMessages();
             btn.disabled = true;
             fetch("{{ route('my-profile.password') }}", {
                 method: 'POST',
                 headers: {
                     'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                     'Accept': 'application/json',
                     'X-Requested-With': 'XMLHttpRequest',
                 },
                 body: new FormData(form),
             }).then(async res => {
                 const data = await res.json().catch(() => ({}));
                 if (res.ok) {
                     form.reset();
                     okBox.classList.remove('d-none');
                     setTimeout(() => bootstrap.Modal.getOrCreateInstance(modalEl).hide(), 1500);
                     return;
                 }
                 if (data.errors && !Array.isArray(data.errors)) {
                     Object.entries(data.errors).forEach(([field, messages]) => {
                         const slot = form.querySelector('.cp-err[data-for="' + field + '"]');
                         if (slot) slot.textContent = messages[0];
                         if (form.elements[field]) form.elements[field].classList.add('is-invalid');
                     });
                 } else {
                     errBox.textContent = res.status === 429
                         ? 'Too many attempts. Please wait a minute and try again.'
                         : (data.message || 'Could not change the password. Please try again.');
                     errBox.classList.remove('d-none');
                 }
             }).catch(() => {
                 errBox.textContent = 'Could not reach the server. Please try again.';
                 errBox.classList.remove('d-none');
             }).finally(() => { btn.disabled = false; });
         });
     })();
 </script>
 <!--! ================================================================ !-->
 <!--! [End] Header !-->
 <!--! ================================================================ !-->
