<div id="nga-preloader">
    <div class="preloader-content">
        <div class="breathing-logo">
            <?php 
                // Check if the logo exists in the current directory's assets folder 
                // or one level up (for admin/teacher folders)
                $logoPath = file_exists('assets/images/logo.png') ? 'assets/images/logo.png' : '../assets/images/logo.png';
            ?>
            <img src="<?php echo $logoPath; ?>" alt="NGA Logo">
        </div>
        <div class="loading-bar">
            <div class="loading-progress"></div>
        </div>
        <p class="loading-text">New Generation Academy</p>
    </div>
</div>

<style>
/* Keep your existing CSS here */
#nga-preloader {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: #ffffff; display: flex; align-items: center; justify-content: center;
    z-index: 9999; transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.preloader-content { text-align: center; }

.breathing-logo img {
    width: 110px; 
    height: 110px;
    filter: drop-shadow(0 0 0px rgba(255, 102, 0, 0));
    animation: breathe 2.5s cubic-bezier(0.37, 0, 0.63, 1) infinite;
}

@keyframes breathe {
    0% { transform: scale(0.9); opacity: 0.8; filter: drop-shadow(0 0 0px rgba(255, 102, 0, 0)); }
    50% { transform: scale(1.15); opacity: 1; filter: drop-shadow(0 10px 20px rgba(255, 102, 0, 0.2)); }
    100% { transform: scale(0.9); opacity: 0.8; filter: drop-shadow(0 0 0px rgba(255, 102, 0, 0)); }
}

.loading-bar {
    width: 180px; height: 3px; background: #f4f6f8;
    margin: 30px auto 15px; border-radius: 10px; overflow: hidden;
    position: relative;
}

.loading-progress {
    width: 40%; height: 100%; background: #FF6600;
    position: absolute; border-radius: 10px;
    animation: slideProgress 2s cubic-bezier(0.445, 0.05, 0.55, 0.95) infinite;
}

@keyframes slideProgress {
    0% { left: -40%; }
    50% { left: 100%; }
    100% { left: 100%; }
}

.loading-text {
    font-family: 'Public Sans', sans-serif;
    color: #919eab; font-weight: 600; font-size: 0.75rem;
    letter-spacing: 2px; text-transform: uppercase;
}
</style>

<script>
window.addEventListener('load', function() {
    const randomTime = Math.floor(Math.random() * (2500 - 1200 + 1)) + 1200;
    setTimeout(function() {
        const loader = document.getElementById('nga-preloader');
        if(loader) {
            loader.style.opacity = '0';
            setTimeout(() => { loader.style.display = 'none'; }, 800);
        }
    }, randomTime);
});
</script>