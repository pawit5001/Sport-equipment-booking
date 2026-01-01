<footer class="site-footer">
    <div class="footer-main">
        <div class="container">
            <div class="footer-grid">
                <!-- Logo & About -->
                <div class="footer-brand">
                    <div class="footer-logo">
                        <i class="fa fa-futbol-o"></i>
                        <span>E-Sports</span>
                    </div>
                    <p class="footer-desc">
                        ระบบยืม-คืนอุปกรณ์กีฬา<br>
                        มหาวิทยาลัยเทคโนโลยีราชมงคลตะวันออก
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-links">
                    <h6><i class="fa fa-link me-2"></i>ลิงก์ด่วน</h6>
                    <ul>
                        <li><a href="dashboard.php"><i class="fa fa-home"></i> หน้าหลัก</a></li>
                        <li><a href="my-bookings.php"><i class="fa fa-history"></i> ประวัติการยืม</a></li>
                        <li><a href="my-profile.php"><i class="fa fa-user"></i> โปรไฟล์</a></li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div class="footer-contact">
                    <h6><i class="fa fa-envelope me-2"></i>ติดต่อเรา</h6>
                    <ul>
                        <li>
                            <i class="fa fa-envelope-o"></i>
                            <a href="mailto:pawit.wee@rmutto.ac.th">pawit.wee@rmutto.ac.th</a>
                        </li>
                        <li>
                            <i class="fa fa-map-marker"></i>
                            <span>มทร.ตะวันออก เขตพื้นที่บางพระ</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
/* Sticky Footer Layout */
html, body {
    height: 100%;
}
body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.content-wrapper {
    flex: 1 0 auto;
}
.site-footer {
    flex-shrink: 0;
}

/* Footer Styles */
.site-footer {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0c4a6e 100%);
    color: #e2e8f0;
    margin-top: auto;
}

.footer-main {
    padding: 2.5rem 0 2rem;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1.5fr;
    gap: 2.5rem;
    align-items: start;
}

.footer-brand .footer-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.footer-brand .footer-logo i {
    font-size: 2rem;
    color: #38bdf8;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.footer-brand .footer-logo span {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, #38bdf8, #818cf8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.footer-desc {
    color: #94a3b8;
    font-size: 0.9rem;
    line-height: 1.7;
    margin: 0;
}

.footer-links h6,
.footer-contact h6 {
    color: #f1f5f9;
    font-weight: 600;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.footer-links ul,
.footer-contact ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links ul li {
    margin-bottom: 0.6rem;
}

.footer-links ul li a {
    color: #94a3b8;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.footer-links ul li a:hover {
    color: #38bdf8;
    transform: translateX(5px);
}

.footer-links ul li a i {
    font-size: 0.8rem;
    width: 16px;
}

.footer-contact ul li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.8rem;
    color: #94a3b8;
    font-size: 0.9rem;
}

.footer-contact ul li i {
    color: #38bdf8;
    margin-top: 0.2rem;
    width: 16px;
}

.footer-contact ul li a {
    color: #94a3b8;
    text-decoration: none;
    transition: color 0.3s ease;
}

.footer-contact ul li a:hover {
    color: #38bdf8;
}

.footer-bottom {
    background: rgba(0, 0, 0, 0.2);
    padding: 1rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.footer-bottom p {
    margin: 0;
    font-size: 0.85rem;
    color: #64748b;
}

.footer-time {
    background: rgba(56, 189, 248, 0.1);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    color: #38bdf8 !important;
}

/* Responsive */
@media (max-width: 768px) {
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
        text-align: center;
    }
    
    .footer-brand .footer-logo {
        justify-content: center;
    }
    
    .footer-links ul li a {
        justify-content: center;
    }
    
    .footer-links ul li a:hover {
        transform: none;
    }
    
    .footer-contact ul li {
        justify-content: center;
        text-align: center;
    }
    
    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<!-- Shared PDF export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
