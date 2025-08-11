<?php
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    // Envoyer l'email (simulation)
    $to = ADMIN_EMAIL;
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $email_body = "Nom: $name\n";
    $email_body .= "Email: $email\n\n";
    $email_body .= "Message:\n$message";
    
    if (mail($to, $subject, $email_body, $headers)) {
        $_SESSION['success_message'] = "Votre message a été envoyé avec succès!";
    } else {
        $_SESSION['error_message'] = "Une erreur s'est produite lors de l'envoi de votre message.";
    }
    
    header("Location: /contact.php");
    exit();
}
?>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <h2 class="fw-bold mb-4">Contactez-nous</h2>
            <p class="lead">Nous sommes là pour répondre à vos questions et vous aider.</p>
            
            <div class="mt-5">
                <div class="d-flex mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center me-3">
                        <i class="fas fa-map-marker-alt fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Adresse</h5>
                        <p class="text-muted mb-0">Libreville, Gabon</p>
                    </div>
                </div>
                
                <div class="d-flex mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center me-3">
                        <i class="fas fa-phone fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Téléphone</h5>
                        <p class="text-muted mb-0"><?= SUPPORT_PHONE ?></p>
                    </div>
                </div>
                
                <div class="d-flex mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center me-3">
                        <i class="fas fa-envelope fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Email</h5>
                        <p class="text-muted mb-0"><?= ADMIN_EMAIL ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-4">Envoyez-nous un message</h4>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom complet</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Sujet</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Envoyer le message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>