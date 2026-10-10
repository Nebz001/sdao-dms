/**
 * The one decorative blue of the app: a soft wash of the NU navy with navy
 * (light theme) or its lighter tint (dark theme) as the glyph or text. The
 * values live in resources/css/app.css (--brand-soft*); these strings are the
 * only place the utilities are spelled, so every icon tile, date badge, role
 * pill and initials circle reads the same. Status colors are separate and
 * never use these.
 */
export const BRAND_TINT = 'bg-brand-soft text-brand-soft-foreground';

/** The same tint with a border, for pills and chips. */
export const BRAND_TINT_OUTLINE = `${BRAND_TINT} border-brand-soft-border`;
