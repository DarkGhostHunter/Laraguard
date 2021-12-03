<?php

namespace DarkGhostHunter\Laraguard\Eloquent;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

use function array_values;
use function chunk_split;
use function config;
use function http_build_query;
use function rawurlencode;
use function strtoupper;
use function trim;

trait SerializesSharedSecret
{
    /**
     * Returns the Shared Secret as a URI.
     *
     * @return string
     */
    public function toUri(): string
    {
        $issuer = config('laraguard.issuer', config('app.name'));

        $query = http_build_query([
            'issuer'    => $issuer,
            'label'     => $this->label,
            'secret'    => $this->shared_secret,
            'algorithm' => strtoupper($this->algorithm),
            'digits'    => $this->digits,
        ], null, '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.rawurlencode($issuer).'%3A'.$this->label."?$query";
    }

    /**
     * Returns the Shared Secret as a QR Code in SVG format.
     *
     * @return string
     */
    public function toQr(): string
    {
        [$size, $margin] = array_values(config('laraguard.qr_code'));

        return (new Writer(new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd)))
            ->writeString($this->toUri());
    }

    /**
     * Returns the current object instance as a string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns the Shared Secret as a string.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->shared_secret;
    }

    /**
     * Returns the Shared Secret as a string of 4-character groups.
     *
     * @return string
     */
    public function toGroupedString(): string
    {
        return trim(chunk_split($this->toString(), 4, ' '));
    }

    /**
     * Get the evaluated contents of the object.
     *
     * @return string
     */
    public function render(): string
    {
        return $this->toQr();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toUri(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return string
     */
    public function jsonSerialize(): string
    {
        return $this->toUri();
    }
}
