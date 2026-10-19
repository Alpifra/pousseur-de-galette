<?php

namespace App\Service;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeRenderer
{
    private const SIZE = 512;

    public function svg(string $content): string
    {
        $style = new RendererStyle(
            self::SIZE,
            fill: Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(18, 18, 18)),
        );
        $writer = new Writer(new ImageRenderer($style, new SvgImageBackEnd()));

        // Printed codes get scanned from a distance, often partly hidden: highest redundancy level
        return $writer->writeString($content, ecLevel: ErrorCorrectionLevel::H());
    }
}
