<footer class="admin-footer">
    <div class="container-fluid">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> E-Sports Equipment System - Admin Panel</p>
            <p class="footer-time">
                <i class="fa fa-clock-o me-1"></i>
                <?php echo date('d/m/Y H:i'); ?> น.
            </p>
        </div>
    </div>
</footer>

<style>
.admin-footer {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: #94a3b8;
    padding: 1rem 0;
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,0.1);
}
.admin-footer .footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.admin-footer p {
    margin: 0;
    font-size: 0.85rem;
}
.admin-footer .footer-time {
    background: rgba(59, 130, 246, 0.15);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    color: #60a5fa;
}
@media (max-width: 576px) {
    .admin-footer .footer-content {
        flex-direction: column;
        text-align: center;
    }
}
</style>
