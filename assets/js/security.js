/**
 * Security Script - Prevent Code Inspection & Tampering
 * Protects against common client-side attacks and unauthorized code inspection
 */

(function() {
    'use strict';

    // Disable right-click context menu (but allow on form elements and interactive components)
    document.addEventListener('contextmenu', function(e) {
        // Allow right-click on input fields, textareas, selects, and buttons for better UX
        const target = e.target;
        if (target.tagName === 'INPUT' || 
            target.tagName === 'TEXTAREA' || 
            target.tagName === 'SELECT' || 
            target.tagName === 'BUTTON' ||
            target.tagName === 'A' ||
            target.closest('button') ||
            target.closest('a') ||
            target.closest('[x-data]') ||
            target.closest('[x-show]') ||
            target.closest('nav')) {
            return true;
        }
        e.preventDefault();
        return false;
    });

    // Disable F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U, Ctrl+S
    document.addEventListener('keydown', function(e) {
        // Allow arrow keys and enter for select/input/button elements
        const target = e.target;
        if (target.tagName === 'INPUT' || 
            target.tagName === 'TEXTAREA' || 
            target.tagName === 'SELECT' || 
            target.tagName === 'BUTTON' ||
            target.closest('[x-data]')) {
            // Allow normal keyboard navigation in form elements and Alpine.js components
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.key === 'Enter' || e.key === 'Escape' || e.key === 'Tab' || e.key === ' ') {
                return true;
            }
        }
        
        // F12 - DevTools
        if (e.keyCode === 123) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl+Shift+I - DevTools
        if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl+Shift+J - Console
        if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl+Shift+C - Inspector
        if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl+U - View Source
        if (e.ctrlKey && e.keyCode === 85) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl+S - Save Page
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl+P - Print (optional, comment out if you want printing)
        if (e.ctrlKey && e.keyCode === 80) {
            e.preventDefault();
            return false;
        }
    });

    // Detect DevTools opening (monitor window size change)
    let devtoolsOpen = false;
    const threshold = 160;
    
    const detectDevTools = () => {
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;
        
        if (widthThreshold || heightThreshold) {
            if (!devtoolsOpen) {
                devtoolsOpen = true;
                handleDevToolsOpen();
            }
        } else {
            devtoolsOpen = false;
        }
    };

    const handleDevToolsOpen = () => {
        // Redirect to login page or show warning
        document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#1a1a1a;color:#fff;font-family:Arial,sans-serif;"><div style="text-align:center;"><h1 style="font-size:48px;margin-bottom:20px;">⚠️</h1><h2>Unauthorized Access Detected</h2><p>Developer tools are not allowed. Please close them to continue.</p><button onclick="location.reload()" style="margin-top:20px;padding:10px 20px;background:#0066cc;color:#fff;border:none;border-radius:5px;cursor:pointer;">Reload Page</button></div></div>';
    };

    // Check periodically
    setInterval(detectDevTools, 1000);

    // Disable text selection (optional - uncomment if needed)
    // document.addEventListener('selectstart', function(e) {
    //     e.preventDefault();
    //     return false;
    // });

    // Disable copy (optional - uncomment if needed)
    // document.addEventListener('copy', function(e) {
    //     e.preventDefault();
    //     return false;
    // });

    // Disable cut (optional - uncomment if needed)
    // document.addEventListener('cut', function(e) {
    //     e.preventDefault();
    //     return false;
    // });

    // Disable drag and drop (optional - uncomment if needed)
    // document.addEventListener('dragstart', function(e) {
    //     e.preventDefault();
    //     return false;
    // });

    // Clear console periodically
    if (typeof console !== 'undefined') {
        setInterval(function() {
            try {
                console.clear();
            } catch(e) {}
        }, 5000); // Clear every 5 seconds
    }

    // Detect console.log calls and override
    const noop = function() {};
    ['log', 'debug', 'info', 'trace', 'dir', 'dirxml', 'group', 'groupCollapsed', 'groupEnd', 'time', 'timeEnd', 'profile', 'profileEnd', 'count', 'table'].forEach(function(method) {
        if (typeof console !== 'undefined' && console[method]) {
            console[method] = noop;
        }
    });

    // Detect debugger statement
    setInterval(function() {
        try {
            debugger;
        } catch(e) {}
    }, 1000); // Run every 1 second

    // Prevent iframe embedding (Clickjacking protection)
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Add watermark to discourage screenshots (optional)
    function addWatermark() {
        const watermark = document.createElement('div');
        watermark.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:100px;color:rgba(0,0,0,0.05);pointer-events:none;z-index:9999;white-space:nowrap;user-select:none;';
        watermark.textContent = 'CONFIDENTIAL - ' + (new Date().toLocaleDateString());
        document.body.appendChild(watermark);
    }

    // Uncomment to enable watermark
    // if (document.readyState === 'loading') {
    //     document.addEventListener('DOMContentLoaded', addWatermark);
    // } else {
    //     addWatermark();
    // }

    // Detect screenshot tools (basic detection)
    document.addEventListener('keyup', function(e) {
        // PrtScn key
        if (e.key === 'PrintScreen') {
            navigator.clipboard.writeText('');
            alert('Screenshots are not allowed for security reasons.');
        }
    });

    // Monitor for suspicious activity
    let suspiciousActivity = 0;
    const maxSuspiciousActivity = 5;

    const logSuspiciousActivity = () => {
        suspiciousActivity++;
        if (suspiciousActivity >= maxSuspiciousActivity) {
            // Log to server
            fetch('/api/security/log_suspicious_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'devtools_detected',
                    timestamp: Date.now(),
                    url: window.location.href
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.action === 'logout' || data.action === 'block') {
                    // Force logout if server indicates too many violations
                    window.location.href = '/api/auth/logout.php';
                }
            })
            .catch(err => {
                console.error('Security logging failed:', err);
                // Still logout on error to be safe
                window.location.href = '/api/auth/logout.php';
            });
        }
    };

    // Override toString to prevent detection of this script
    const originalToString = Function.prototype.toString;
    Function.prototype.toString = function() {
        if (this === detectDevTools || this === handleDevToolsOpen) {
            return 'function () { [native code] }';
        }
        return originalToString.call(this);
    };

    // Protect against common hacking attempts
    Object.freeze(console);
    
    // Prevent modification of this script
    if (document.currentScript) {
        Object.freeze(document.currentScript);
    }

})();
