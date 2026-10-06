import { describePasswordRules } from '@/lib/password-rules';

/**
 * Small muted line under a new-password field stating the full rule in plain
 * words. Point the input's aria-describedby at `id` so screen readers read it
 * with the field.
 */
export default function PasswordRuleHint({
    id,
    rules,
}: {
    id: string;
    rules: string;
}) {
    return (
        <p id={id} className="text-xs text-muted-foreground">
            {describePasswordRules(rules)}
        </p>
    );
}
