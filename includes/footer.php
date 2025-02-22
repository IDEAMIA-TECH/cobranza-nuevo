    </main>
    <footer class="footer">
        <div class="footer-content">
            <div class="company-info">
                <p class="company-name"><?php echo Settings::get('company_name', 'IDEAMIA Tech'); ?></p>
                <p class="company-address">
                    <?php 
                        $address = Settings::get('company_address');
                        $city = Settings::get('company_city');
                        $state = Settings::get('company_state');
                        $zip = Settings::get('company_zip');
                        
                        echo $address ? htmlspecialchars($address) : '';
                        echo ($city || $state) ? '<br>' : '';
                        echo $city ? htmlspecialchars($city) : '';
                        echo ($city && $state) ? ', ' : '';
                        echo $state ? htmlspecialchars($state) : '';
                        echo $zip ? ' ' . htmlspecialchars($zip) : '';
                    ?>
                </p>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> <?php echo Settings::get('company_name', 'IDEAMIA Tech'); ?>. 
                   Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <style>
        .footer {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .company-info {
            margin-bottom: 1rem;
        }

        .company-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .company-address {
            font-size: 0.9rem;
            opacity: 0.9;
            line-height: 1.5;
        }

        .copyright {
            font-size: 0.85rem;
            opacity: 0.8;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
            margin-top: 1rem;
        }
    </style>
</body>
</html> 