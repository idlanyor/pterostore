// Antidonasi Store JavaScript

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(event) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            event.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 70
            }, 1000);
        }
    });

    // Form validation
    $('.needs-validation').on('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        $(this).addClass('was-validated');
    });

    // Password strength meter
    $('#password').on('input', function() {
        var password = $(this).val();
        var strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        var strengthText = '';
        var strengthClass = '';
        
        switch(strength) {
            case 0:
            case 1:
                strengthText = 'Sangat Lemah';
                strengthClass = 'bg-danger';
                break;
            case 2:
                strengthText = 'Lemah';
                strengthClass = 'bg-warning';
                break;
            case 3:
                strengthText = 'Sedang';
                strengthClass = 'bg-info';
                break;
            case 4:
                strengthText = 'Kuat';
                strengthClass = 'bg-success';
                break;
            case 5:
                strengthText = 'Sangat Kuat';
                strengthClass = 'bg-success';
                break;
        }
        
        $('#password-strength').text(strengthText).removeClass().addClass('badge ' + strengthClass);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut();
    }, 5000);

    // Confirm delete actions
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Apakah Anda yakin ingin menghapus item ini?')) {
            e.preventDefault();
        }
    });

    // Order status update
    $('.update-status').on('change', function() {
        var orderId = $(this).data('order-id');
        var newStatus = $(this).val();
        var button = $(this).siblings('.btn-update-status');
        
        button.prop('disabled', false).text('Update Status');
    });

    // Server action buttons
    $('.server-action').on('click', function(e) {
        e.preventDefault();
        var action = $(this).data('action');
        var serverId = $(this).data('server-id');
        var button = $(this);
        
        if (confirm('Apakah Anda yakin ingin ' + action + ' server ini?')) {
            button.prop('disabled', true).html('<span class="loading"></span> Processing...');
            
            $.ajax({
                url: 'server_actions.php',
                method: 'POST',
                data: {
                    action: action,
                    server_id: serverId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                        button.prop('disabled', false).text(action);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                    button.prop('disabled', false).text(action);
                }
            });
        }
    });

    // QRIS payment timer
    if ($('#payment-timer').length) {
        var timeLeft = 900; // 15 minutes in seconds
        var timer = setInterval(function() {
            var minutes = Math.floor(timeLeft / 60);
            var seconds = timeLeft % 60;
            
            $('#payment-timer').text(
                (minutes < 10 ? '0' : '') + minutes + ':' + 
                (seconds < 10 ? '0' : '') + seconds
            );
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                $('#payment-timer').text('Waktu habis');
                $('#payment-expired').show();
            }
            timeLeft--;
        }, 1000);
    }

    // Auto-refresh order status
    if ($('#order-status').length) {
        setInterval(function() {
            var orderId = $('#order-id').val();
            $.ajax({
                url: 'check_order_status.php',
                method: 'POST',
                data: { order_id: orderId },
                success: function(response) {
                    if (response.status !== $('#order-status').text()) {
                        location.reload();
                    }
                }
            });
        }, 30000); // Check every 30 seconds
    }

    // Product search and filter
    $('#product-search').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.product-card').each(function() {
            var productName = $(this).find('.card-header h5').text().toLowerCase();
            var productDesc = $(this).find('.card-body').text().toLowerCase();
            
            if (productName.includes(searchTerm) || productDesc.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Category filter
    $('.category-filter').on('click', function(e) {
        e.preventDefault();
        var category = $(this).data('category');
        
        $('.category-filter').removeClass('active');
        $(this).addClass('active');
        
        if (category === 'all') {
            $('.product-card').show();
        } else {
            $('.product-card').each(function() {
                var productCategory = $(this).data('category');
                if (productCategory === category) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });

    // Copy to clipboard functionality
    $('.btn-copy').on('click', function(e) {
        e.preventDefault();
        var text = $(this).data('clipboard-text');
        
        navigator.clipboard.writeText(text).then(function() {
            var button = $(e.target);
            var originalText = button.text();
            button.text('Copied!').addClass('btn-success').removeClass('btn-outline-secondary');
            
            setTimeout(function() {
                button.text(originalText).removeClass('btn-success').addClass('btn-outline-secondary');
            }, 2000);
        });
    });

    // File upload preview
    $('#payment-proof').on('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#proof-preview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // Server console
    if ($('#server-console').length) {
        var console = $('#server-console');
        var serverId = console.data('server-id');
        
        // Connect to WebSocket for real-time console
        // This is a placeholder - implement actual WebSocket connection
        console.append('<div class="console-line">[INFO] Connecting to server console...</div>');
    }

    // Chart initialization for dashboard
    if ($('#orders-chart').length) {
        var ctx = document.getElementById('orders-chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Orders',
                    data: [12, 19, 3, 5, 2, 3],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Notification system
    function showNotification(message, type = 'info') {
        var alertClass = 'alert-' + type;
        var notification = $('<div class="alert ' + alertClass + ' alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>');
        
        $('body').append(notification);
        
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    }

    // Global notification function
    window.showNotification = showNotification;

    // Keyboard shortcuts
    $(document).keydown(function(e) {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 75) {
            e.preventDefault();
            $('#product-search').focus();
        }
        
        // Escape to close modals
        if (e.keyCode === 27) {
            $('.modal').modal('hide');
        }
    });

    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    // Performance monitoring
    if (window.performance && window.performance.timing) {
        window.addEventListener('load', function() {
            var loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
            console.log('Page load time: ' + loadTime + 'ms');
        });
    }
}); 