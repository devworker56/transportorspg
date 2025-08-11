<?php
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="fw-bold mb-4">Mentions Légales</h1>
                    
                    <h2 class="h4 fw-bold mt-4">1. Éditeur du site</h2>
                    <p>Le site <strong>Transporteur Provincial Gabonais (TPG)</strong> est édité par :</p>
                    <p>
                        Société TPG SARL<br>
                        Immatriculée au Registre du Commerce et du Crédit Mobilier de Libreville sous le numéro RC/XXXX<br>
                        Siège social : Libreville, Gabon<br>
                        Téléphone : <?= SUPPORT_PHONE ?><br>
                        Email : <?= ADMIN_EMAIL ?>
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">2. Directeur de la publication</h2>
                    <p>Le directeur de la publication est Monsieur [Nom du directeur], en sa qualité de [fonction].</p>
                    
                    <h2 class="h4 fw-bold mt-4">3. Hébergement</h2>
                    <p>
                        Le site est hébergé par :<br>
                        [Nom de l'hébergeur]<br>
                        [Adresse de l'hébergeur]<br>
                        [Téléphone de l'hébergeur]<br>
                        [Site web de l'hébergeur]
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">4. Propriété intellectuelle</h2>
                    <p>
                        L'ensemble des éléments constituant le site (textes, images, vidéos, logos, etc.) sont la propriété exclusive de TPG ou de ses partenaires et sont protégés par les lois en vigueur sur la propriété intellectuelle.
                    </p>
                    <p>
                        Toute reproduction, représentation, modification, publication, adaptation totale ou partielle des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite sans autorisation préalable et écrite de TPG.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">5. Données personnelles</h2>
                    <p>
                        Conformément à la loi n°001/2011 du 21 septembre 2011 relative à la protection des données à caractère personnel, les informations que vous nous communiquez sont destinées à TPG et peuvent faire l'objet d'un traitement informatique.
                    </p>
                    <p>
                        Vous disposez d'un droit d'accès, de rectification et de suppression des données vous concernant. Pour exercer ce droit, adressez-vous à <?= ADMIN_EMAIL ?>.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">6. Cookies</h2>
                    <p>
                        Le site peut utiliser des cookies pour améliorer l'expérience utilisateur. En naviguant sur ce site, vous acceptez l'utilisation des cookies conformément à notre politique de confidentialité.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">7. Responsabilité</h2>
                    <p>
                        TPG ne peut garantir l'exactitude et l'exhaustivité des informations diffusées sur son site. En conséquence, TPG décline toute responsabilité pour tout dommage résultant d'une utilisation du site ou des informations qui y sont disponibles.
                    </p>
                    
                    <p class="mt-5 text-muted">Dernière mise à jour : <?= date('d/m/Y') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>