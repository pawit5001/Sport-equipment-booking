<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #7c2d12 0%, #b45309 100%); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
    <style>
        .navbar-nav .nav-link,
        .navbar-nav .nav-link:link,
        .navbar-nav .nav-link:visited,
        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link:focus,
        .navbar-nav .nav-link:active {
            text-decoration: none !important;
            text-decoration-line: none !important;
            text-decoration-style: none !important;
            border-bottom: none !important;
        }
        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link:focus,
        .navbar-nav .nav-link:active {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border-radius: 6px !important;
        }
        .navbar-nav .nav-link::before,
        .navbar-nav .nav-link::after {
            display: none !important;
        }
        .navbar-brand,
        .navbar-brand:hover,
        .navbar-brand:focus {
            text-decoration: none !important;
            text-decoration-line: none !important;
        }
        .navbar-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.25) !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
        }
        .dropdown-toggle::after {
            vertical-align: 0.15em !important;
        }
        .dropdown-menu {
            background-color: white !important;
            border: none !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.05) !important;
            padding: 0.4rem !important;
            min-width: 170px !important;
            max-width: 220px !important;
            right: 0 !important;
            left: auto !important;
            z-index: 1050 !important;
            margin-top: 0.55rem !important;
            display: none !important;
        }
        .dropdown-menu.show {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            animation: dropdownFadeIn 0.2s ease-out !important;
        }
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .dropdown-menu .dropdown-item {
            text-decoration: none !important;
            padding: 0.55rem 0.85rem !important;
            color: #374151 !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            border-radius: 6px !important;
            transition: all 0.15s ease !important;
            display: flex !important;
            align-items: center !important;
            margin: 1px 0 !important;
            white-space: normal !important;
            line-height: 1.3 !important;
        }
        .dropdown-menu .dropdown-item:hover,
        .dropdown-menu .dropdown-item:focus,
        .dropdown-menu .dropdown-item:active {
            text-decoration: none !important;
            background-color: #f3f4f6 !important;
            color: #b45309 !important;
            transform: translateX(2px) !important;
            outline: none !important;
        }
        .dropdown-menu .dropdown-item:active {
            background-color: #e5e7eb !important;
        }
        .dropdown-menu .dropdown-item i {
            color: #6b7280 !important;
            font-size: 1rem !important;
            width: 18px !important;
            transition: color 0.15s ease !important;
        }
        .dropdown-menu .dropdown-item:hover i {
            color: #b45309 !important;
        }
        .dropdown-divider {
            margin: 0.5rem 0 !important;
            border-color: #e5e7eb !important;
            opacity: 1 !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
            const pageMap = {
                'dashboard.php': 'dashboard',
                'manage-suppliers.php': 'suppliers',
                'add-supplier.php': 'suppliers',
                'manage-categories.php': 'categories',
                'add-category.php': 'categories',
                'manage-equipment.php': 'equipment',
                'add-equipment.php': 'equipment',
                'manage-issued-equipment.php': 'bookings',
                'reg-students.php': 'members',
                'change-password.php': 'account'
            };

            const currentSection = pageMap[currentPage];
            if (currentSection) {
                document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
                    const href = link.getAttribute('href');
                    if (!href) return;
                    const linkPage = href.split('/').pop();
                    if (pageMap[linkPage] === currentSection) {
                        link.classList.add('active');
                    }
                });
            }

            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            dropdownToggles.forEach(toggle => {
                const parentDropdown = toggle.closest('.dropdown');
                const parentMenu = parentDropdown ? parentDropdown.querySelector('.dropdown-menu') : null;
                if (!parentDropdown || !parentMenu) return;

                const handleToggle = (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const open = parentDropdown.classList.contains('show') || parentMenu.classList.contains('show');
                    document.querySelectorAll('.dropdown').forEach(d => {
                        d.classList.remove('show');
                        d.querySelector('.dropdown-menu')?.classList.remove('show');
                    });
                    
                    if (!open) {
                        parentDropdown.classList.add('show');
                        parentMenu.classList.add('show');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                };

                toggle.addEventListener('click', handleToggle);

                document.addEventListener('click', function(ev) {
                    if (!parentDropdown.contains(ev.target)) {
                        parentDropdown.classList.remove('show');
                        parentMenu.classList.remove('show');
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            });
        });
    </script>
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php" style="font-weight: 700; font-size: 1.3rem;">
            <i class="fa fa-shield" style="font-size: 1.8rem; margin-right: 8px;"></i>
            <span style="margin-left: 5px; font-weight: 700; color: white;">ระบบยืม-คืนอุปกรณ์กีฬา</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-white" href="dashboard.php" style="transition: all 0.3s;">
                        <i class="fa fa-home"></i> หน้าแรก
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="javascript:void(0);" role="button" aria-expanded="false" style="transition: all 0.3s; cursor: pointer;">
                        <i class="fa fa-user"></i> ผู้รับผิดชอบ
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="add-supplier.php"><i class="fa fa-plus me-2"></i>เพิ่มผู้รับผิดชอบ</a></li>
                        <li><a class="dropdown-item" href="manage-suppliers.php"><i class="fa fa-users me-2"></i>จัดการผู้รับผิดชอบ</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="javascript:void(0);" role="button" aria-expanded="false" style="transition: all 0.3s; cursor: pointer;">
                        <i class="fa fa-folder"></i> หมวดหมู่
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="add-category.php"><i class="fa fa-plus me-2"></i>เพิ่มหมวดหมู่</a></li>
                        <li><a class="dropdown-item" href="manage-categories.php"><i class="fa fa-edit me-2"></i>จัดการหมวดหมู่</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="javascript:void(0);" role="button" aria-expanded="false" style="transition: all 0.3s; cursor: pointer;">
                        <i class="fa fa-archive"></i> อุปกรณ์กีฬา
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="add-equipment.php"><i class="fa fa-plus me-2"></i>เพิ่มอุปกรณ์</a></li>
                        <li><a class="dropdown-item" href="manage-equipment.php"><i class="fa fa-edit me-2"></i>จัดการอุปกรณ์</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="booking-settings.php"><i class="fa fa-money me-2"></i>ตั้งค่าค่าปรับ</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="manage-issued-equipment.php" style="transition: all 0.3s;">
                        <i class="fa fa-exchange"></i> รายการยืม/คืน
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="reg-students.php" style="transition: all 0.3s;">
                        <i class="fa fa-users"></i> นักศึกษา
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="javascript:void(0);" id="adminAccountDropdown" role="button" aria-expanded="false" style="transition: all 0.3s; cursor: pointer;">
                        <i class="fa fa-user"></i> บัญชีผู้ใช้
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminAccountDropdown">
                        <li><a class="dropdown-item" href="change-password.php"><i class="fa fa-key me-2"></i>เปลี่ยนรหัสผ่าน</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out me-2"></i>ออกจากระบบ</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>