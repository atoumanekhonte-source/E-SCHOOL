<?php
// Pied de page HTML
require_once __DIR__ . '/helpers.php'; // Pour get_base_url() et autres
require_once __DIR__ . '/auth.php'; // Pour is_logged_in(), get_user_name(), etc.
$base_url = get_base_url(); // Assurez-vous que helpers.php est inclus ou que la fonction est disponible
?>
        </div> <!-- Fin .container (ouvert dans header.php) -->
    </main> <!-- Fin .main-content -->

    <footer style="background-color: var(--main-bg-start); color: var(--text-muted); padding: 30px 0; text-align: center; border-top: 1px solid var(--border-color); margin-top: auto;">
        <div class="container" style="padding-top:0; padding-bottom:0;">
            <p style="margin-bottom: 10px;">
                © <?php echo date('Y'); ?> MonÉcoleTECH - Tous droits réservés.
            </p>
            <p style="margin-bottom: 10px;">
                📞 Contact: 333333333
            </p>
            <div class="social-icons" style="margin-bottom: 10px;">
                <!-- Remplacez par de vraies icônes/liens -->
                <a href="#" style="color: var(--text-light); margin: 0 10px; text-decoration: none; font-size: 1.5em;">f</a>
                <a href="#" style="color: var(--text-light); margin: 0 10px; text-decoration: none; font-size: 1.5em;">t</a>
                <a href="#" style="color: var(--text-light); margin: 0 10px; text-decoration: none; font-size: 1.5em;">in</a>
            </div>
            <p style="font-size: 0.8em;">
                <a href="<?php echo $base_url; ?>terms.php" style="color: var(--text-muted); text-decoration:none;">Conditions d'utilisation</a> | 
                <a href="<?php echo $base_url; ?>privacy.php" style="color: var(--text-muted); text-decoration:none;">Politique de confidentialité</a>
            </p>
        </div>
    </footer>

    <!-- Scripts JS (si vous en avez) -->
    <!-- <script src="<?php echo $base_url; ?>assets/js/script.js"></script> -->
    <!-- Exemple: <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script> -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script> -->
    <!-- <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> -->

    <script>
        // Petit script pour la navigation active (alternative à la fonction PHP is_active_page si besoin)
        // Ou pour d'autres interactivités simples
        document.addEventListener('DOMContentLoaded', function() {
            // Exemple: rendre les cartes de cours cliquables si elles ont un data-href
            const courseCards = document.querySelectorAll('.course-card[data-href]');
            courseCards.forEach(card => {
                card.addEventListener('click', () => {
                    window.location.href = card.dataset.href;
                });
            });
        });
    </script>
</body>
</html>