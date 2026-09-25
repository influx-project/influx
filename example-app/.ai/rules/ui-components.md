# UI Component Conventions

- This project standardizes on [shadcn/ui](https://ui.shadcn.com) for all UI components. `components.json` is already configured (style: new-york, base color: neutral, icons: lucide, RSC: false).
- Before building any UI, check `resources/js/components/ui/` for an existing shadcn component first, then check `resources/js/components/` for an existing custom component. Do not duplicate either.
- To add a new shadcn component, run `bunx shadcn@latest add <component>` rather than hand-rolling a primitive shadcn already provides (dialog, dropdown-menu, select, sheet, tooltip, etc.).
- Only write a fully custom component when shadcn/ui has no equivalent primitive or block. Custom components should still be built on shadcn primitives and `cn()` (from `resources/js/lib/utils.ts`) where possible, and follow the same composition style (Radix-based, `class-variance-authority` for variants) as the existing `components/ui` files.
- Activate the `ui-ux-pro-max` skill (and its `ui-styling` / `design-system` sub-skills, under `.claude/skills/`) whenever planning, building, or reviewing UI/UX — layouts, styling, visual hierarchy, accessibility, or design-system decisions — don't wait until asked.
