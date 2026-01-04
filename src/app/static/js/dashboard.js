/**
 * 仪表盘页面交互逻辑
 * @file dashboard.js
 */

window.pageLoadFiles = [];

window.pageOnLoad = function (loading) {
    /**
     * 数字动画效果
     */
    function animateNumbers() {
        document.querySelectorAll('.animate-number').forEach(el => {
            const target = parseInt(el.getAttribute('data-value')) || 0;
            if (target === 0) {
                el.textContent = '0';
                return;
            }
            
            let current = 0;
            const duration = 1000; // 1秒
            const steps = 30;
            const increment = Math.ceil(target / steps);
            const interval = duration / steps;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = current.toLocaleString();
            }, interval);
        });
    }
    
    // 初始化
    animateNumbers();
};
