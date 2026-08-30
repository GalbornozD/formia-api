<?php

namespace App\Enums;

/**
 * Cada caso corresponde a una imagen de company_branding. `column()`/`slug()`
 * evitan repetir el mapeo en el Service, el Controller y las rutas.
 */
enum BrandingAssetType: string
{
    case Logo = 'logo';
    case LogoDark = 'logo_dark';
    case LogoCompact = 'logo_compact';
    case Favicon = 'favicon';

    public function column(): string
    {
        return match ($this) {
            self::Logo => 'logo_path',
            self::LogoDark => 'logo_dark_path',
            self::LogoCompact => 'logo_compact_path',
            self::Favicon => 'favicon_path',
        };
    }

    /**
     * Nombre de archivo fijo (sin datos del usuario) para la ruta en disco.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Logo => 'logo',
            self::LogoDark => 'logo-dark',
            self::LogoCompact => 'logo-compact',
            self::Favicon => 'favicon',
        };
    }

    /**
     * Favicon admite archivos más livianos que los logos.
     */
    public function maxKilobytes(): int
    {
        return $this === self::Favicon ? 1024 : 2048;
    }
}
