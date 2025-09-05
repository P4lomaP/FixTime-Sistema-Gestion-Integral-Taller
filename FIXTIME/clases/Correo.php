<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ajustá estas rutas si tu PHPMailer no está en /phpmailer/src/
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';
require_once __DIR__ . '/../phpmailer/Exception.php';

class Correo
{
    private PHPMailer $mail;

    public function __construct()
    {
        $conf = require __DIR__ . '/../config/correo.php';
        $smtp = $conf['smtp'];

        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host       = $smtp['host'];
        $m->SMTPAuth   = $smtp['auth'];
        $m->Username   = $smtp['usuario'];
        $m->Password   = $smtp['clave'];
        $m->SMTPSecure = $smtp['secure'];
        $m->Port       = $smtp['port'];
        $m->CharSet    = 'UTF-8';
        $m->setFrom($smtp['from_email'], $smtp['from_name'] ?? 'Fixtime');

        $this->mail = $m;
    }

    /** Render simple de plantilla HTML con {{CLAVES}} */
    public function renderPlantilla(string $ruta, array $vars): string
    {
        if (!is_file($ruta)) {
            throw new RuntimeException("Plantilla no encontrada: $ruta");
        }
        $html = file_get_contents($ruta);
        foreach ($vars as $clave => $valor) {
            $html = str_replace('{{' . $clave . '}}', $valor, $html);
        }
        return $html;
    }

    /**
     * Envía correo HTML y embebe un logo por CID si se provee.
     */
    public function enviarHtmlConLogo(
        string $paraEmail,
        string $paraNombre,
        string $asunto,
        string $plantillaHtml,
        string $cidLogo = 'logo-fixtime',
        ?string $rutaLogo = null,
        string $textoPlano = ''
    ): bool {
        $this->mail->clearAddresses();
        $this->mail->clearAttachments();

        $this->mail->addAddress($paraEmail, $paraNombre ?: $paraEmail);
        $this->mail->isHTML(true);
        $this->mail->Subject = $asunto;
        $this->mail->Body    = $plantillaHtml;
        $this->mail->AltBody = $textoPlano ?: strip_tags($plantillaHtml);

        if ($rutaLogo && is_file($rutaLogo)) {
            $this->mail->addEmbeddedImage($rutaLogo, $cidLogo);
        }

        return $this->mail->send();
    }
}
