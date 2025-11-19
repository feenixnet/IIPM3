<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package IPM
 */

get_header();
?>

<style>
.error-404-container {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 140px 40px 20px 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.error-404-content {
    text-align: center;
    background: white;
    padding: 60px 40px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 600px;
    width: 100%;
}

.error-404-icon {
    font-size: 120px;
    margin-bottom: 20px;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-20px);
    }
    60% {
        transform: translateY(-10px);
    }
}

.error-404-title {
    font-size: 72px;
    font-weight: 900;
    color: #667eea;
    margin: 0 0 10px 0;
    line-height: 1;
}

.error-404-subtitle {
    font-size: 28px;
    color: #1f2937;
    margin: 0 0 20px 0;
    font-weight: 600;
}

.error-404-message {
    font-size: 16px;
    color: #6b7280;
    margin: 0 0 40px 0;
    line-height: 1.6;
}

.error-404-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.error-404-btn {
    padding: 14px 30px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.error-404-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.error-404-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

.error-404-btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.error-404-btn-secondary:hover {
    background: #e5e7eb;
    transform: translateY(-2px);
}

.error-404-suggestions {
    margin-top: 50px;
    padding-top: 30px;
    border-top: 2px solid #e5e7eb;
}

.error-404-suggestions h3 {
    font-size: 18px;
    color: #374151;
    margin: 0 0 20px 0;
    font-weight: 600;
}

.error-404-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
    text-align: left;
}

.error-404-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: #f9fafb;
    border-radius: 8px;
    text-decoration: none;
    color: #374151;
    font-size: 15px;
    transition: all 0.2s ease;
}

.error-404-link:hover {
    background: #f3f4f6;
    transform: translateX(5px);
}

.error-404-link i {
    color: #667eea;
    font-size: 18px;
}

@media (max-width: 768px) {
    .error-404-title {
        font-size: 56px;
    }
    
    .error-404-subtitle {
        font-size: 22px;
    }
    
    .error-404-content {
        padding: 40px 25px;
    }
    
    .error-404-actions {
        flex-direction: column;
    }
    
    .error-404-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="error-404-container">
    <div class="error-404-content">
        <div class="error-404-icon">🔍</div>
        <h1 class="error-404-title">404</h1>
        <h2 class="error-404-subtitle">Page Not Found</h2>
        <p class="error-404-message">
            Oops! The page you're looking for doesn't exist. It might have been moved or deleted.
        </p>
        
        <div class="error-404-actions">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="error-404-btn error-404-btn-primary">
                <i class="fas fa-home"></i>
                Go to Homepage
            </a>
            <?php if (is_user_logged_in()): ?>
            <?php else: ?>
                <a href="<?php echo esc_url(home_url('/login/')); ?>" class="error-404-btn error-404-btn-secondary">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
get_footer();
