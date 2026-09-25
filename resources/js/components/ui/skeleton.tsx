import { cn } from "@/lib/utils"

function Skeleton({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="skeleton"
      className={cn("animate-shimmer rounded-md bg-linear-to-r from-primary/5 via-primary/15 to-primary/5 bg-[length:200%_100%]", className)}
      {...props}
    />
  )
}

export { Skeleton }
