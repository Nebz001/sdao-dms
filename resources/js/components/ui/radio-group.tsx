import { Check } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * A choice of one, built on native radio inputs rather than a Radix
 * primitive: the browser already gives one tab stop for the whole group,
 * arrow-key switching, and the "radio group, 1 of 2" announcement, and the
 * selected value is submitted with the surrounding form under `name`.
 */
function RadioGroup({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      role="radiogroup"
      data-slot="radio-group"
      className={cn("@container grid auto-rows-fr gap-3", className)}
      {...props}
    />
  )
}

type RadioGroupCardProps = Omit<React.ComponentProps<"input">, "type" | "title"> & {
  icon: React.ReactNode
  title: React.ReactNode
  description?: React.ReactNode
}

/**
 * One option row: icon, bold title, muted description and a radio dot on the
 * right. The title and description sit on separate lines, and share one line
 * only when the group is wide enough to fit both without crowding.
 */
function RadioGroupCard({
  className,
  icon,
  title,
  description,
  ...props
}: RadioGroupCardProps) {
  const titleId = React.useId()
  const descriptionId = React.useId()

  return (
    <label
      data-slot="radio-group-card"
      className={cn(
        "group/card border-input hover:bg-accent/50 flex min-h-[4.25rem] cursor-pointer items-center gap-3 rounded-lg border px-3.5 py-3 transition-colors",
        "has-[:checked]:border-primary-text has-[:checked]:bg-primary/10",
        "has-[:focus-visible]:focus-ring",
        "has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50",
        className
      )}
    >
      <input
        type="radio"
        className="sr-only"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        {...props}
      />
      <span
        aria-hidden
        className="bg-muted text-muted-foreground group-has-[:checked]/card:text-primary-text flex size-9 shrink-0 items-center justify-center rounded-md [&_svg]:size-4"
      >
        {icon}
      </span>
      <span className="flex min-w-0 flex-1 flex-col gap-0.5 @lg:flex-row @lg:flex-wrap @lg:items-baseline @lg:gap-x-2">
        <span id={titleId} className="text-sm leading-snug font-semibold">
          {title}
        </span>
        {description ? (
          <span
            id={descriptionId}
            className="text-muted-foreground text-xs leading-snug font-normal"
          >
            {description}
          </span>
        ) : null}
      </span>
      <span
        aria-hidden
        className="border-input text-primary-foreground group-has-[:checked]/card:border-primary group-has-[:checked]/card:bg-primary flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
      >
        <Check className="size-3 opacity-0 group-has-[:checked]/card:opacity-100" strokeWidth={3} />
      </span>
    </label>
  )
}

export { RadioGroup, RadioGroupCard }
