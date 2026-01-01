<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e40af 0%, #0891b2 100%); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
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
            color: #1e40af !important;
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
            color: #1e40af !important;
        }
        .dropdown-divider {
            margin: 0.5rem 0 !important;
            border-color: #e5e7eb !important;
            opacity: 1 !important;
        }
    </style>
    <script>
        // Active state + dropdown init (defer until Bootstrap is ready)
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[header] DOMContentLoaded');
            const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
            const pageMap = {
                'dashboard.php': 'dashboard',
                'book-equipment.php': 'book-equipment',
                'my-bookings.php': 'my-bookings',
                'my-profile.php': 'account',
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

            const dropdownToggle = document.getElementById('accountDropdown');
            const dropdownElement = dropdownToggle ? dropdownToggle.closest('.dropdown') : null;

            console.log('[header] dropdownToggle', !!dropdownToggle, 'dropdownElement', !!dropdownElement);

            // Guarded handler so the dropdown works even if other scripts rebind events
            if (dropdownToggle && dropdownElement) {
                const handleToggle = (e) => {
                    const toggleEl = e.target.closest('#accountDropdown');
                    if (!toggleEl) return;

                    console.log('[header] toggle click', {
                        target: e.target.tagName,
                        hasBootstrap: !!(window.bootstrap && window.bootstrap.Dropdown)
                    });

                    e.preventDefault();
                    e.stopPropagation();

                    const parentDropdown = toggleEl.closest('.dropdown');
                    const parentMenu = parentDropdown ? parentDropdown.querySelector('.dropdown-menu') : null;
                    if (!parentMenu) return;

                    console.log('[header] before toggle', {
                        parentHasShow: parentDropdown.classList.contains('show'),
                        menuHasShow: parentMenu.classList.contains('show')
                    });

                    const open = parentDropdown.classList.contains('show') || parentMenu.classList.contains('show');
                    parentDropdown.classList.toggle('show', !open);
                    parentMenu.classList.toggle('show', !open);
                    toggleEl.setAttribute('aria-expanded', open ? 'false' : 'true');

                    console.log('[header] after toggle', {
                        nowOpen: !open,
                        parentHasShow: parentDropdown.classList.contains('show'),
                        menuHasShow: parentMenu.classList.contains('show')
                    });

                    if (!open) {
                        document.addEventListener('click', function close(ev) {
                            if (!parentDropdown.contains(ev.target)) {
                                parentDropdown.classList.remove('show');
                                parentMenu.classList.remove('show');
                                toggleEl.setAttribute('aria-expanded', 'false');
                                document.removeEventListener('click', close);
                                console.log('[header] outside click close');
                            }
                        });
                    }
                };

                // Listen in both capture and bubble phases to win against other handlers
                document.addEventListener('click', handleToggle, true);
                document.addEventListener('click', handleToggle, false);
            }
        });
    </script>
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php" style="font-weight: 700; font-size: 1.3rem;">
            <img src="assets/img/RMUTTO.png" alt="Logo" height="50" style="filter: brightness(1.1);">
            <span style="margin-left: 10px; font-weight: 700; color: white;">ระบบยืม-คืนอุปกรณ์กีฬา</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if(isset($_SESSION['login']) && $_SESSION['login']){ ?>
            <ul id="menu-top" class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-white" href="dashboard.php" style="transition: all 0.3s;">
                        <i class="fa fa-home"></i> หน้าแรก
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white position-relative" href="book-equipment.php" style="transition: all 0.3s;">
                        <i class="fa fa-shopping-cart"></i> ตะกร้า
                        <?php 
                        $cartCount = 0;
                        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                            foreach ($_SESSION['cart'] as $val) {
                                $cartCount += is_array($val) ? (int)($val['quantity'] ?? 1) : (int)$val;
                            }
                        }
                        ?>
                        <span id="cartBadge" class="cart-badge badge bg-danger rounded-pill" style="font-size: 0.7rem; margin-left: 5px;<?php echo $cartCount > 0 ? '' : ' display: none;'; ?>"><?php echo $cartCount; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="my-bookings.php" style="transition: all 0.3s;">
                        <i class="fa fa-history"></i> ประวัติการยืม
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="javascript:void(0);" id="accountDropdown" role="button" aria-expanded="false" style="transition: all 0.3s; cursor: pointer;">
                        <i class="fa fa-user"></i> บัญชีผู้ใช้
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountDropdown">
                        <li><a class="dropdown-item" href="my-profile.php"><i class="fa fa fa-user"></i>โปรไฟล์ของฉัน</a></li>
                        <li><a class="dropdown-item" href="change-password.php"><i class="fa fa-key me-2"></i>เปลี่ยนรหัสผ่าน</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa fa-sign-out me-2"></i>ออกจากระบบ</a></li>
                    </ul>
                </li>
            </ul>
            <?php } else { ?>
            <ul id="menu-top" class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-white" href="index.php" style="transition: all 0.3s;">
                        <i class="fa fa-sign-in"></i> เข้าสู่ระบบ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="signup.php" style="transition: all 0.3s;">
                        <i class="fa fa-user-plus"></i> ลงทะเบียน
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="adminlogin.php" style="transition: all 0.3s;">
                        <i class="fa fa-lock"></i> สำหรับเจ้าหน้าที่
                    </a>
                </li>
            </ul>
            <?php } ?>
        </div>
    </div>
</nav>