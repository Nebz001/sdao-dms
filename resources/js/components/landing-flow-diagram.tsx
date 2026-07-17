/**
 * Decorative hero background for the landing page: an abstract routing path
 * — nodes connected by curved segments, tinted navy → info → success. This
 * visualizes what the product actually does (a document's journey through
 * approval to Approved) rather than a generic decorative shape. Purely
 * decorative: aria-hidden, no interaction, respects prefers-reduced-motion
 * via the `motion-safe:` variant (reduced-motion users see the static
 * end-state immediately).
 */
export default function LandingFlowDiagram({ className }: { className?: string }) {
    const segments = [
        { d: 'M60,300 C140,300 170,160 250,150', color: 'text-primary/15', delay: '0ms' },
        { d: 'M250,150 C330,140 370,260 450,250', color: 'text-primary/20', delay: '250ms' },
        { d: 'M450,250 C530,240 550,110 620,100', color: 'text-info/25', delay: '500ms' },
        { d: 'M620,100 C670,85 700,160 740,180', color: 'text-success/30', delay: '750ms' },
    ];

    const nodes = [
        { cx: 60, cy: 300, color: 'text-primary/25', r: 7 },
        { cx: 250, cy: 150, color: 'text-primary/30', r: 7 },
        { cx: 450, cy: 250, color: 'text-info/35', r: 7 },
        { cx: 620, cy: 100, color: 'text-info/40', r: 7 },
        { cx: 740, cy: 180, color: 'text-success/60', r: 9 },
    ];

    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 800 400"
            className={className}
            style={{ pointerEvents: 'none' }}
        >
            {segments.map((s) => (
                <path
                    key={s.d}
                    d={s.d}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    strokeLinecap="round"
                    className={`${s.color} motion-safe:animate-[dash_1.6s_ease-out_forwards]`}
                    style={{ strokeDasharray: 1000, strokeDashoffset: 1000, animationDelay: s.delay }}
                />
            ))}
            {nodes.map((n, i) => (
                <circle
                    key={`${n.cx}-${n.cy}`}
                    cx={n.cx}
                    cy={n.cy}
                    r={n.r}
                    fill="currentColor"
                    className={`${n.color} motion-safe:animate-[fade-in_0.4s_ease-out_forwards]`}
                    style={{ opacity: 0, animationDelay: `${i * 250 + 200}ms` }}
                />
            ))}
        </svg>
    );
}
