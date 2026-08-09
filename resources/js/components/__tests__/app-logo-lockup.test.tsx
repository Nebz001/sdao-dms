import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import AppLogoLockup from '@/components/app-logo-lockup';

/**
 * AppLogoLockup renders BOTH theme variants and lets CSS (`dark:hidden` /
 * `hidden dark:block`) pick one, rather than switching `src` off
 * useAppearance().resolvedAppearance. That hook always resolves to 'light'
 * on first hydration (see its own file for why), so a JS-driven swap would
 * flash the wrong logo for dark-theme users on every load — this test pins
 * the CSS-only approach so a future edit can't reintroduce that flash.
 */
describe('AppLogoLockup', () => {
    it('renders exactly one image per theme, each pointing at its own asset', () => {
        render(<AppLogoLockup />);

        const images = screen.getAllByAltText('NU Lipa');
        expect(images).toHaveLength(2);

        const light = images.find((img) =>
            img.getAttribute('src')?.includes('light-bg'),
        );
        const dark = images.find((img) =>
            img.getAttribute('src')?.includes('dark-bg'),
        );

        expect(light).toBeDefined();
        expect(dark).toBeDefined();
        expect(light?.getAttribute('src')).toBe(
            '/images/logo/nulp-logo-light-bg.svg',
        );
        expect(dark?.getAttribute('src')).toBe(
            '/images/logo/nulp-logo-dark-bg.svg',
        );
    });

    it('hides the light variant in dark theme and vice versa, via CSS only', () => {
        render(<AppLogoLockup />);

        const images = screen.getAllByAltText('NU Lipa');
        const light = images.find((img) =>
            img.getAttribute('src')?.includes('light-bg'),
        );
        const dark = images.find((img) =>
            img.getAttribute('src')?.includes('dark-bg'),
        );

        expect(light?.className).toContain('dark:hidden');
        expect(light?.className).not.toContain('hidden ');
        expect(dark?.className).toContain('hidden');
        expect(dark?.className).toContain('dark:block');
    });

    it('carries the intrinsic 6250x4434 aspect ratio to prevent layout shift', () => {
        render(<AppLogoLockup />);

        for (const img of screen.getAllByAltText('NU Lipa')) {
            expect(img.getAttribute('width')).toBe('6250');
            expect(img.getAttribute('height')).toBe('4434');
        }
    });

    it('applies a caller-supplied className to both images, for height-based sizing', () => {
        render(<AppLogoLockup className="h-10" />);

        for (const img of screen.getAllByAltText('NU Lipa')) {
            expect(img.className).toContain('h-10');
        }
    });
});
