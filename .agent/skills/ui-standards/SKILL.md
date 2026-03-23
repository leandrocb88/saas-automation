---
name: YouTube SaaS UI Standards
description: Guidelines for consistent, premium UI/UX across the YouTube analysis SaaS platform.
---

# YouTube SaaS UI Standards

This document outlines the core design patterns and implementation rules to maintain a premium, consistent user experience across all new UI components.

## Core Layout Structure

### 1. Page Backgrounds
- Use the global `AuthenticatedLayout` background (typically `bg-gray-100 dark:bg-gray-900`) as the primary canvas.
- **Do not** wrap main content areas in secondary solid backgrounds like `bg-gray-50` or `bg-white`.
- Maintain a **transparent** aesthetic for page bodies to ensure seamless transitions.

### 2. Hero Sections
- Use rich, evocative gradients:
  - `bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-100` (Light)
  - `dark:from-indigo-600 dark:via-purple-600 dark:to-indigo-800` (Dark)
- Include subtle decorative elements like large, blurred blobs (`blur-3xl opacity-60`).

---

## Component Styles

### 1. Cards (Premium Texture)
- Use a **Glassmorphism** effect for primary content containers:
  ```html
  <div className="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-[2rem] border border-gray-200/50 dark:border-gray-700/50 shadow-xl shadow-gray-200/20 dark:shadow-none">
  ```
- Radius should be generous (e.g., `rounded-[2rem]` for large cards, `rounded-xl` for small items).

### 2. Accordions (Channel Lists)
- **Outer Wrapper**: Transparent background to blend with page body.
- **Header**: High contrast background when collapsed (`bg-white dark:bg-gray-800`), thin indigo border when expanded.
- **Body**: Always `bg-transparent`.
- **Borders**: Subte indigo borders (`border-indigo-100 dark:border-indigo-800/20`).

### 3. Typography
- **Headers**: `font-black`, `tracking-tight`, `leading-tight`.
- **Labels**: Small, uppercase, high tracking (e.g., `text-[9px] font-black uppercase tracking-widest text-gray-400`).
- **Contrast**: Use `text-gray-900 dark:text-white` for primary text and `text-gray-500 dark:text-gray-400` for secondary.

---

## Interactive Elements

### 1. Buttons
- **Primary**: Indigo theme (`bg-indigo-600 hover:bg-indigo-500`).
- **Success**: Emerald theme (`text-emerald-500 bg-emerald-500/10`).
- **Danger**: Red theme (`text-red-500 bg-red-500/10`).
- **Micro-animations**: Use `transition-all duration-300` and subtle scaling (`hover:scale-[1.02]`).

### 2. Flash Messages (Toasts)
- Fixed position: `bottom-6 right-6 z-50`.
- Style: Floating glassmorphism cards with auto-dismiss (5s).

---

## Best Practices
- **Consistency**: Before creating a new page, reference `DigestRun.tsx` or `Summary.tsx` for existing patterns.
- **Dark Mode**: Always use `dark:` variants for every color/shadow class.
- **Spacings**: Use consistent padding (`p-6` or `p-8`) and vertical spacing (`space-y-10` or `space-y-12`) between sections.
