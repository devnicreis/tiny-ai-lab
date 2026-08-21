# AI LAB - UI Kit & Design System Guidelines

This document serves as the absolute source of truth for the Visual Identity (IDV) and UI Components of the AI LAB project. Any future development or AI code generation MUST adhere strictly to these rules.

## 1. Interface Language (Mandatory Rule)
**English Only**: Absolutely all text generated in the interface code — including page titles, card names, field labels, placeholders, helper text, buttons, and status messages — MUST be written strictly in English.

## 2. Visual Identity, Exclusive Palette & Dual Theme (Default Light Mode)
**Primary Default Theme**: The Light theme is the primary, default preset for the entire interface. The Dark theme is maintained as a variant. You must ALWAYS generate elements for the Light theme as the base, providing their Dark mode versions using Tailwind CSS utilities with the `dark:` prefix.

**Strict Brand Color Palette**:
*   **Primary Accent**: `#d2691e` (Orange)
*   **Secondary/Support**: `#9e6136` (Muted Orange)
*   **Dark Accent**: `#6b4e3a` (Brown)
*   **Dark Background**: `#121214` (Deep Gray/Black)
*   **Dark Card Background**: `#1c1c1c` (Dark Gray)
*   **Light Text/Background**: `#f4f4f6` (Off-white)

**Application Rules**:
*   **Main Screen & Menus Background**: Light uses `bg-gray-50` | Dark uses `dark:bg-[#121214]`.
*   **Containers/Cards/Dropdowns Background**: Light uses `bg-white` | Dark uses `dark:bg-[#1c1c1c]`.
*   **Hover Backgrounds (Dynamic Interaction)**: Use the secondary color with soft opacity: `hover:bg-[#6b4e3a]/20` (or `dark:hover:bg-[#6b4e3a]/20`).
*   **Borders & Dividers**: Light uses `border-gray-200` | Dark uses `dark:border-[#6b4e3a]/30`.
*   **Main Texts & Titles**: Light uses `text-gray-900` | Dark uses `dark:text-[#f4f4f6]`.
*   **Secondary/Muted Texts**: Light uses `text-gray-500` | Dark uses `dark:text-[#9e6136]` (or `dark:text-[#f4f4f6]/60`).
*   **Highlights & Strong Actions**: Use the primary accent (`#d2691e`) for primary buttons, active icons, focus states (`focus:ring-[#d2691e]`), active steps, and selected borders (`hover:border-[#d2691e]`).

## 3. Icon & SVG Guidelines
When inserting any vector graphic element (`<svg>`):
*   **Stroke Compensation**: Always add `stroke="currentColor" stroke-width="0.3"` to the `<svg>` tag to ensure an elegant and proportional stroke.
*   **Sizing**:
    *   *Main Icons* (Header, Search, Notification, Ask AI, Global Actions): Use `w-5 h-5` (20x20px).
    *   *Secondary/Internal Icons* (Dropdowns, Filters, Inputs, Badges): Use `w-4 h-4` (16x16px).
*   **Dynamic Color**: Always declare `fill="currentColor"` in the `<svg>` and control the color dynamically via text classes on the parent/sibling tag (e.g., `class="w-5 h-5 text-[#9e6136] hover:text-[#d2691e]"`).
*   **Premium Interactions (Glow & Scale)**: For highlight icons or "Group" buttons, apply the transition: `class="transition-all duration-300 group-hover:drop-shadow-[0_0_6px_#d2691e] group-hover:scale-110"`.

## 4. Typography & Content Hierarchy
*   **Font Families**:
    *   *Titles & Headers*: Use the editorial font **Newsreader** (`font-newsreader` or `font-serif`).
    *   *Body Text & Interface*: Use the font **Inter** (`font-sans`).
*   **Typography Scale (Tokens)**:
    *   **Titles (Newsreader)**:
        *   **H1 (Main Page Titles)**: `text-4xl tracking-tight`
        *   **H2 (Block Titles)**: `text-3xl tracking-tight`
        *   **H3 (Card/Modal Titles)**: `text-2xl`
        *   **H4 (Subsections)**: `text-xl` or `text-lg`
        *   **H5 (Minor Support Titles)**: `text-base` or `text-sm`
    *   **Body & UI (Inter)**:
        *   **Standard Text (Inputs, Buttons, Dropdowns)**: `text-sm`
        *   **Long Support Texts (Paragraphs/Descriptions)**: `text-[13px] leading-relaxed`
        *   **Micro-copy (Tags, Badges, Tooltips, Small Buttons)**: `text-xs`
        *   **Absolute Micro (Overlay Badges)**: `text-[11px]`
*   **Title Style**: Replicate the high-contrast editorial pattern, using the Newsreader font and combining thin and bold weights in the same header phrase (e.g., "The official guidelines and essential pillars of your brand.").
*   **Form Fields**: Clean and minimalist inputs wrapped in standardized backgrounds (`dark:bg-[#1c1c1c]`, `dark:border-[#6b4e3a]/30`). Use clear placeholders, small help/tooltip icons next to labels, and discreet focus states in the brand color (`#d2691e`).

## 5. Layout Structure & Code Standards
*   **Component Reuse**: Whenever applicable, consult and reuse the standardized code snippets from this `ui-kit.md` file.
*   **Strict Modularity**: NEVER rewrite the Sidebar or the Topbar (Header). Assume they already exist in the master layout. Focus ONLY on generating the `<main>` tag or the requested isolated component, allowing the developer to easily port the code to Laravel blade views.
*   **Responsiveness**: Use Tailwind utility classes (`md:`, `lg:`) to ensure everything is 100% responsive. On mobile, cards and grids must stack vertically (`grid-cols-1`).

---

## 6. Official Component Library

The following HTML snippets represent the official, approved UI components for the system. Use them as references or copy-paste them directly to ensure absolute consistency.

### Button Regenerate

```html
<button type="button" class="w-full max-w-sm inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-xl border border-gray-200 dark:border-[#f4f4f6]/20 bg-white dark:bg-[#1c1c1c] text-gray-700 dark:text-[#A8A29E] hover:bg-gray-50 dark:hover:bg-[#6b4e3a]/20 px-4 py-2.5 transition-colors shadow-sm group">
  <svg class="w-4 h-4 group-hover:drop-shadow-[0_0_6px_#d2691e] group-hover:scale-110 transition-all duration-300" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 27.69 27.71"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <g id="bow">
    <g>
      <path class="cls-1" d="M17.88,27.12c-2.68.81-5.41.77-8.08.01l-1.51-.43c-.9-.26-1.77-.5-2.7-.61-1.36-.16-2.68.09-3.82.83-.08.05-.19.01-.23-.05-.04-.06-.03-.13.02-.22l.46-.92c.27-.54.38-1.71.28-2.4-.11-.82-.33-1.59-.6-2.36l-.71-2.01c-1.31-3.67-1.32-6.5-.02-10.18.31-.89.62-1.73.87-2.64.26-.96.38-1.93.18-2.92-.12-.6-.39-1.15-.69-1.67-.04-.07-.01-.17.03-.2s.13-.03.2.01c2.15,1.31,4.21.65,6.42-.12l1.29-.45c2.81-.98,5.85-1.08,8.7-.18l2.77.88c1.92.61,3.76.74,5.48-.39.07-.04.15-.01.19.02.04.03.1.11.05.17-.22.32-.37.66-.52,1.02-.39.91-.43,1.88-.25,2.86.37,1.96,1.22,3.77,1.68,5.83s.44,4.11-.08,6.14c-.25.99-.57,1.89-.92,2.84s-.68,1.87-.88,2.87c-.19.91-.15,2.16.25,2.97l.42.87c.03.07.05.16,0,.21-.04.05-.16.07-.24.02-1.04-.72-2.26-1.07-3.52-.94-.75.08-1.45.22-2.17.44l-2.34.7ZM18.21,26.66l1.91-.58c.72-.22,1.43-.37,2.19-.43h1.19c.73.08,1.39.29,2.05.63-.27-.58-.44-1.14-.5-1.75-.03-.3-.11-.6,0-.9.04-.72.18-1.41.42-2.1l1-2.89c1.24-3.58,1.13-6.23-.07-9.8-.34-1-.66-1.96-.92-2.98-.22-.87-.3-1.75-.16-2.64.08-.51.29-.94.45-1.45-.6.29-1.18.46-1.82.53h-1.24c-.91-.09-1.74-.33-2.6-.6l-2.51-.8c-2.48-.79-5.46-.66-7.93.17l-2.98,1.01c-.62.21-1.23.31-1.88.38-.97.1-1.9-.05-2.82-.44.27.63.41,1.24.47,1.89.02.24.08.46-.01.7-.03.76-.19,1.48-.43,2.19l-.81,2.4C.2,12.15.05,14.62.93,17.64l1.03,3.02c.31.9.58,1.76.7,2.71v1.24c-.07.58-.25,1.11-.48,1.68,1.79-.81,3.45-.66,5.3-.18l2.39.68c3,.86,5.34.79,8.34-.13Z"/>
      <path class="cls-1" d="M23.04,12.94c-.24-3.1-1.99-5.86-4.67-7.36-.09-.05-.17-.09-.21-.14s.07-.3.14-.26c2.03.98,3.71,2.6,4.72,4.63.57,1.16.87,2.36.96,3.64v1.33c-.11,1.14-.34,2.24-.82,3.29-1.5,3.3-4.67,5.6-8.28,5.96-5.86.59-10.97-3.87-11.27-9.7-.02-.32-.02-.54,0-.85.12-2.81,1.34-5.42,3.45-7.28,1.51-1.33,3.31-2.21,5.31-2.47l-.23-1.48,3.17,1.9c.11.06.2.12.18.2l-3.28,2.25.18-1.48c-3.01.49-5.47,2.45-6.67,5.18-1.07,2.38-1.01,5.14.16,7.47.4.8.86,1.5,1.47,2.14.21.22.4.44.64.64.81.7,1.72,1.26,2.73,1.66,3.46,1.37,7.29.43,9.84-2.22l.22-.24c1.64-1.81,2.46-4.32,2.26-6.82ZM23.04,16.36c-.47,1.67-1.42,3.13-2.67,4.32l-.85.68c-2.7,2-6.28,2.4-9.34,1.01s-5.03-4.04-5.48-7.25c-.42-3.03.68-6,2.92-8.07,1.36-1.26,3.09-2.05,4.93-2.33.1-.01.17.03.21.08.04.04.02.11.01.18l-.11.89,2.29-1.56-2.33-1.39.13.78c.01.11.02.2-.02.32-1.53.16-2.97.67-4.24,1.51-4.47,2.92-5.87,8.79-3.11,13.38,3.16,5.26,10.29,6.45,14.98,2.36,1.95-1.7,3.13-4.03,3.28-6.62.08-1.42-.11-2.89-.71-4.16.59,2.07.69,3.78.11,5.86Z"/>
      <circle class="cls-1" cx="13.85" cy="13.86" r="1.38"/>
    </g>
  </g></svg>
  Regenerate
</button>
```

### Button Back

```html
<button type="button" class="px-3 py-1.5 bg-white dark:bg-[#1c1c1c] text-xs font-semibold text-gray-600 dark:text-[#A8A29E] hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-[#2a2a2a] rounded-lg flex items-center gap-1.5 transition-colors border border-gray-200 dark:border-[#f4f4f6]/10 shadow-sm">
  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
  Back to Grid
</button>
```

### Button Generate Ai

```html
<button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#d2691e]/10 text-[#d2691e] hover:bg-[#d2691e] hover:text-white rounded-lg text-xs font-bold transition shadow-sm border border-[#d2691e]/30 group" title="Generate with AI">
  Generate with AI
</button>
```

### Image Card

```html
<div class="relative group rounded-xl overflow-hidden bg-gray-100 dark:bg-[#111111] border border-gray-200 dark:border-[#f4f4f6]/20 aspect-square flex flex-col justify-end w-48 shadow-sm">
  <!-- Image Placeholder (Can be replaced with an actual <img> tag) -->
  <div class="absolute inset-0 flex items-center justify-center">
    <svg class="w-8 h-8 text-gray-300 dark:text-[#A8A29E]/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
  </div>
  
  <!-- Delete Button -->
  <button type="button" class="absolute top-2 right-2 p-1.5 flex items-center justify-center bg-white/90 dark:bg-[#1c1c1c]/90 backdrop-blur-sm text-gray-600 dark:text-[#A8A29E] hover:text-red-500 dark:hover:text-red-400 rounded-lg opacity-0 group-hover:opacity-100 transition-all shadow-sm border border-gray-200/50 dark:border-[#f4f4f6]/10 z-20" onclick="this.parentElement.remove()" title="Remove Image">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
  </button>
</div>
```

### Checkbox Text

```html
<div class="flex flex-col gap-1">
  <!-- Checkbox 1 -->
  <label class="flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-[#6b4e3a]/20 rounded-lg cursor-pointer transition-colors select-none">
    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 dark:border-[#6b4e3a]/30 text-[#d2691e] focus:ring-[#d2691e] dark:bg-transparent accent-[#d2691e]" checked>
    <span class="text-sm text-gray-700 dark:text-[#A8A29E]">Resources</span>
  </label>
  <!-- Checkbox 2 -->
  <label class="flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-[#6b4e3a]/20 rounded-lg cursor-pointer transition-colors select-none">
    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 dark:border-[#6b4e3a]/30 text-[#d2691e] focus:ring-[#d2691e] dark:bg-transparent accent-[#d2691e]">
    <span class="text-sm text-gray-700 dark:text-[#A8A29E]">Articles</span>
  </label>
  <!-- Checkbox 3 -->
  <label class="flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-[#6b4e3a]/20 rounded-lg cursor-pointer transition-colors select-none">
    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 dark:border-[#6b4e3a]/30 text-[#d2691e] focus:ring-[#d2691e] dark:bg-transparent accent-[#d2691e]">
    <span class="text-sm text-gray-700 dark:text-[#A8A29E]">Case Studies</span>
  </label>
</div>
```

### Upload Area

```html
<div class="relative flex flex-col justify-center items-center px-4 py-10 bg-gray-50 dark:bg-[#121214] border-2 border-dashed border-gray-300 dark:border-[#f4f4f6]/20 rounded-xl hover:border-[#d2691e] dark:hover:border-[#d2691e] transition-colors cursor-pointer group w-full">
  <div class="text-center z-10 group-hover:opacity-70 transition-opacity flex flex-col items-center">
    <svg class="w-10 h-10 text-gray-400 dark:text-[#A8A29E] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
    <span class="block text-sm font-medium text-gray-500 dark:text-[#A8A29E] px-2 text-center">Click or drop your files here to upload</span>
    <span class="block text-xs font-normal text-gray-400 dark:text-[#A8A29E]/70 mt-1">Supports JPG, PNG or PDF</span>
  </div>
</div>
```

### Tags

```html
<div class="flex flex-wrap gap-2 w-full">
  <label class="cursor-pointer">
    <input type="checkbox" class="peer hidden" checked>
    <span class="inline-flex items-center py-1.5 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-[#f4f4f6]/20 text-gray-500 dark:text-[#A8A29E] peer-checked:bg-[#d2691e] peer-checked:text-white peer-checked:border-[#d2691e] transition-colors">Sci-Fi</span>
  </label>
  <label class="cursor-pointer">
    <input type="checkbox" class="peer hidden">
    <span class="inline-flex items-center py-1.5 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-[#f4f4f6]/20 text-gray-500 dark:text-[#A8A29E] peer-checked:bg-[#d2691e] peer-checked:text-white peer-checked:border-[#d2691e] transition-colors">Cyberpunk</span>
  </label>
  <label class="cursor-pointer">
    <input type="checkbox" class="peer hidden" checked>
    <span class="inline-flex items-center py-1.5 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-[#f4f4f6]/20 text-gray-500 dark:text-[#A8A29E] peer-checked:bg-[#d2691e] peer-checked:text-white peer-checked:border-[#d2691e] transition-colors">Neon Orange</span>
  </label>
</div>
```

### Sidebar List

```html
<div class="flex w-full max-w-sm bg-white dark:bg-[#1c1c1c] rounded-2xl border border-gray-200 dark:border-[#f4f4f6]/20 flex-col overflow-hidden h-[400px] shadow-xl">
          <div class="p-5 border-b border-gray-200 dark:border-[#f4f4f6]/20">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-newsreader text-base font-bold text-gray-900 dark:text-[#f4f4f6]">Brand Characters</h2>
              <button class="flex items-center gap-1.5 text-xs font-semibold text-[#d2691e] hover:text-orange-600 transition-colors" onclick="showNewDetailView()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Mascot
              </button>
            </div>
            <!-- Search Bar -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-400 dark:text-[#A8A29E]" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 24 24"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <g id="search">
    <g>
      <path class="cls-1" d="M18.93,21.57s-.05-.12-.08-.12c-.12-.03-.13-.13-.21-.18l-.26-.17-.2-.26-.3-.3c-.06-.06-.15-.12-.17-.19,0-.01-.05-.01-.06,0-.01,0,0-.05-.02-.07l-.44-.45-.25-.26-.25-.26c-.44-.45-.85-.91-1.26-1.4-.19-.23-.37-.45-.58-.66l-.45-.46-.33-.32-1.02-1.05s-.03-.05-.04-.07c0-.02.02-.04.04-.06l-.35-.46c-.37.25-.71.51-1.1.7l-.41.21-.31.14-.74.21c-.1.03-.2,0-.27-.02-.01.07-.11.04-.14.05-.09.02-.16.05-.26.06l-.27.04c-.1.01-.19.01-.3.01h-.62c-.38.02-.75.03-1.13-.01l-.45-.05c-.08,0-.13-.02-.2-.04l-.6-.12c-.22-.05-.41-.12-.6-.23l-.53-.18-.51-.23c-.64-.35-1.53-.87-2-1.4l-.18-.2c-.19-.16-.31-.34-.44-.54l-.29-.41c-.2-.29-.36-.58-.49-.89l-.15-.35c-.04-.09-.08-.14-.1-.22l-.12-.41c0,.07-.05.07-.06.01,0-.19-.06-.4-.09-.58l-.21-1.06c-.03-.17-.06-.34-.08-.52l-.05-.6c-.05-.62.22-2.37.47-2.67l.09-.32c.07,0,.07-.07.1-.13.1-.17.15-.34.23-.53.04-.1.12-.22.18-.31l.24-.42c.05-.08.11-.17.17-.24.1-.11.12-.23.19-.35.32-.53.83-.95,1.32-1.34.54-.43,1.1-.87,1.71-1.18.21-.11.42-.24.65-.27.07-.01.09-.04.15-.07.16-.07.34-.13.52-.16.35-.08.69-.14,1.05-.18,0,.02.02.05.03.05.04.02.03-.02.04-.03.05-.07.19-.02.26-.04.14-.05.24,0,.36,0,.35-.03.67.04,1.02.08.65.08,1.27.19,1.89.41.1.04.21.04.31.08l.4.15c.24.09.58.25.79.37l.5.3c.08.05.14.08.22.13l.53.36.32.29c.12.11.23.22.34.34s.24.2.32.36l.2.24.6.99.12.23v.09s.09.06.09.06c.01,0,.03.05.03.06,0,.01-.04,0-.07,0l.08.17s.02-.04.04-.06c.09.13.15.26.2.42s.16.3.17.47c0,.08-.04.17,0,.26.09.2.1.41.14.63.05.34.17.65.16.99,0,.07-.02.13-.01.22l.03.47-.02,1.15c0,.23-.07.43-.12.65l-.07.28c-.04.17-.07.33-.13.49l-.21.56c-.13.34-.26.58-.45.88l-.22.34c-.17.27-.35.5-.57.74-.01.01-.02.06-.02.08s.06.02.07.02c.2.06.35.29.51.4.08,0,.22,0,.26.12l.06.05s-.03.04-.04.06c.07.18.26.32.44.36l.03.04s.03,0,.05-.05c0,.07.04.11.09.16l.18.2.27.26.16.19.26.24c.18.17.34.34.52.51l1.21,1.12.3.29c.18.17.33.33.52.49l.35.29.41.42.32.35.53.63.56.67.39.44c.16.17.3.37.32.6s.03.4-.05.61c0,.01-.01.04-.03.03-.01,0-.03-.03-.03-.07-.06.06-.03.13-.02.22.02.1-.07.16-.12.23-.13.16-.25.33-.43.42l-.3.09c-.19.1-.39.12-.59,0l-.43-.24c-.12-.12-.26-.21-.43-.25-.14-.18-.32-.29-.5-.42l-.26-.21ZM6.65.46c.09-.02.19,0,.26-.06l-.51.07c-.08.01-.17.04-.24.08l.02.03.46-.12ZM5.28.84c.31-.08.55-.13.82-.25-.18-.01-.34.06-.52.12-.11.04-.23.06-.3.13ZM7.84,15.73h.45s.62-.09.62-.09l.75-.16c.35-.11.69-.18,1.02-.34.5-.24,1-.46,1.47-.75l.33-.2c.49-.3.95-.63,1.32-1.07l.15-.24.45-.58.19-.29.3-.62.16-.37c.05-.12.1-.19.11-.33,0-.08.03-.2.06-.28.11-.32.17-.63.26-.95l.1-.4c.1-.59.11-1.16-.02-1.74l-.22-1.05-.26-.81-.33-.83-.36-.61c-.37-.51-.82-.92-1.27-1.35-.39-.37-.78-.7-1.23-.98-.3-.18-.57-.37-.88-.53-.77-.39-1.6-.57-2.46-.6-.6-.02-1.17.11-1.76.22-.48.09-.9.24-1.36.38-.66.2-1.28.44-1.85.84l-.42.36c-.17.24-.37.46-.57.68l-.43.46-.4.46-.15.24c-.44.7-.48,1.11-.67,1.85-.13.5-.16.99-.21,1.5l.02,1.16c.04.47.14.92.25,1.37.04.19.08.36.14.54l.26.74c.11.31.24.6.43.88l.16.23c.09.14.22.26.31.39.11.17.66.68.89.86l.81.64c.49.39.81.49,1.31.7l.68.28.58.18.72.13.57.06ZM13.69,2.61c-.17-.19-.35-.33-.55-.47-.08-.05-.12-.16-.24-.15l.45.41.65.6c-.06-.17-.2-.26-.31-.39ZM15.76,6.41c.04-.18-.03-.3-.08-.43-.03-.11-.04-.22-.11-.3l.11.46.09.28ZM.63,10.24l-.15-.63c-.06-.25-.14-.5-.11-.76,0-.01-.04-.05-.05-.04-.02.02,0,.5.08.76l.14.51c.09.34.15.69.33,1.01l-.23-.85ZM3.15,14.18l-.53-.48c-.12-.11-.19-.26-.36-.32.27.34.58.6.89.86.07-.01.09-.08,0-.06ZM19.11,20.55l.38.41.3.37.38.42c.19.21.41.32.68.31.21,0,.35-.1.47-.27.14-.21.2-.47.1-.73-.08-.19-.17-.41-.32-.56l-.36-.35-.45-.41-.18-.17-.33-.32-.36-.34-1.02-1.02-.42-.46-.15-.18-.21-.21-.12-.14-.25-.22c-.27-.32-.56-.59-.87-.88l-.64-.6-.38-.36-.16-.18-.18-.14-.53-.57-.35-.44c-.12.08-.19.19-.28.31l-.12.08c-.08.14-.2.19-.31.29l-.46.41.23.14c.08.05.18.11.26.19l.74.74c.38.38.73.77,1.11,1.16l.34.34c.05.06.16.06.19.16.03.12.16.1.22.16l.26.29-.02-.09c.1.01.15.1.13.19.03,0,.09.02.1.04,0,.01,0,.04,0,.06l.12.09s-.03-.06-.02-.07c0-.01.03-.02.04-.01.02,0,.02.03.03.04l.36.35c-.05.04-.03.07,0,.1s.06.02.1-.02l.44.46.3.31.21.22c.07.07.19.17.24.25.07.12.16.17.24.27.16.18.33.35.51.54ZM11.26,15.31l-.56.22c.22-.01.39-.11.56-.22ZM18.85,20.79l-.36-.41c-.45-.52-.93-.99-1.41-1.48l-.42-.43-.25-.21-.02.02.32.34.43.48.46.51.71.71c.17.17.34.34.54.47Z"/>
      <path class="cls-1" d="M6.45,14.59l-.39-.11-.23-.12c-.35-.09-.65-.25-.96-.41l-.41-.22c-.23-.12-.43-.26-.64-.42l-.47-.38c-.13-.1-.21-.24-.33-.35-.08-.07-.13-.14-.19-.23-.15-.22-.33-.42-.45-.67-.08-.16-.2-.3-.24-.47.02-.01.04-.03.03-.04-.01-.02-.03-.02-.06-.01-.09-.1-.1-.26-.16-.37-.07-.13-.08-.21-.07-.35,0-.02-.47-1.22-.46-2.45v-.21s.05-.78.05-.78c.03-.52.25-1.01.47-1.48l.29-.55c.08-.15.17-.33.27-.45l.42-.51c.39-.47.8-.88,1.29-1.24l.32-.24,1.01-.58.52-.19c.16-.06.3-.07.47-.11.22-.04.42-.1.64-.09l.34-.05h.86c.54.05,1.06.1,1.59.23l.88.28c.12.04.24.1.36.15l.39.2c.37.19.7.46.96.78l.23.28c.05.06.11.13.16.19l.6.82c.42.58.7,1.22.87,1.91.06.25.13.48.18.72.19.97.3,2.09-.07,3.02l-.44,1.1c-.06.14-.18.28-.27.4-.07.08-.06.15-.12.22l-.28.36c-.45.57-.96,1.13-1.56,1.54-.3.21-.59.42-.93.57l-.33.15c-.64.25-1.33.39-2.02.39l-.17-.02-.66-.02-.83-.09-.2-.04-.24-.07ZM5.54,2.26c.46-.18.89-.37,1.29-.44-.17-.05-.32.04-.47.07-.2.04-.36.11-.54.2-.1.05-.24.05-.27.16ZM7.18,14.28l.68.04c.45,0,.87-.02,1.3-.15l.99-.29c.74-.26,1.42-.64,2.03-1.12l.4-.31.22-.23c.33-.34.59-.74.8-1.17l.28-.78.22-.88.11-.63c.09-.52.05-1.03-.08-1.54-.15-.6-.34-1.16-.61-1.72-.1-.21-.16-.43-.3-.62l-.2-.39-.3-.5c-.15-.25-.4-.41-.63-.58l-.3-.23c-.78-.61-1.7-1.02-2.67-1.15l-.63-.09-.9.02c-.31,0-1.33.33-1.7.5l-.53.24c-.49.22-.93.5-1.36.83s-.75.7-1.05,1.14c-.17.24-.37.58-.46.85-.22.6-.4,1.21-.52,1.84-.06.32,0,.84.04,1.2.09.62.23,1.21.44,1.81.11.31.21.59.37.88.29.53.65.98,1.07,1.41.17.17.28.36.48.5.18.12.35.26.54.37.16.1.31.18.48.26l.48.23.2.06c.37.11.7.22,1.09.24ZM11.13,2.46s.02-.05,0-.06l-.38-.16s-.01.02-.02.02l.41.19ZM2.51,11.34c-.08-.26-.18-.46-.29-.68.04.26.16.48.29.68ZM9.22,14.49c.14.05.22-.04.32-.03.57-.08,1.1-.29,1.57-.61l-.55.22c-.45.18-.9.31-1.35.43Z"/>
      <path class="cls-1" d="M6.75,13.32l-.44-.16-.57-.28-.54-.29-.6-.49c-.24-.19-.43-.4-.58-.66-.07-.11-.16-.2-.17-.35h.09c-.06-.09-.08-.12-.08-.2.1.04.09.32.18.25.07-.05-.12-.19-.02-.26.03-.02.09,0,.12.03.18.22.35.43.55.62.58.55,1.16,1.03,1.98,1.16l1.51.23.5.09s.07.08.07.11c0,.04-.06.07-.13.08l.14.04c0,.13-.09.18-.2.21-.38.09-.75.03-1.14-.02l-.64-.13ZM8.31,13.23c-.08,0-.16-.05-.25,0,.16.03.33.07.51,0-.08-.04-.16,0-.26,0Z"/>
      <path class="cls-1" d="M3.41,9.48c0,.08-.07.14-.11.16-.04.03-.16.01-.2-.03-.15-.17-.17-.39-.22-.6-.15-.6-.15-1.15-.03-1.76.03-.13.01-.27.12-.36-.04-.04-.04-.07-.04-.09,0-.02.05-.05.09-.06.06-.39.33-1.1.55-1.39.07-.08.1-.21.2-.27h.07s.04-.17.29-.18c-.05.06-.14.07-.12.19.06-.09.2-.07.24,0l-.15.3c-.14.29-.28.53-.38.87-.07.23-.14.45-.19.7l-.13.69-.02.24.03,1.61Z"/>
      <path class="cls-1" d="M11.73,5.28c-.13.07-.17-.01-.24-.08l-.58-.49-.36-.29s-.15-.02-.13-.14c-.07.05-.1-.02-.14-.03-.36-.13-.71-.47-1.44-.64l-.51-.11c-.12-.03-.25-.15-.18-.27.01-.02.08-.01.09-.02.02,0,.01-.05,0-.07.15-.08.32-.05.48-.04.21.02.4.03.61.09.33.1.67.16.99.31.37.18.72.38,1,.69.06.06.11.1.16.17l.47.56c.14.16-.02.21.05.31-.08,0-.1-.05-.15-.09-.02.03,0,.05,0,.06.02.01.02.05,0,.06-.02,0-.05-.01-.08-.02l.07.18c-.06-.02-.09-.05-.11-.14Z"/>
      <path class="cls-1" d="M12.87,8.56c0-.09-.18-.16-.22-.4-.04-.23-.08-.44-.14-.66-.09-.33-.15-.65-.2-.99-.01-.11-.02-.23.14-.25.02.07.05.13.09.19,0-.12-.07-.22.04-.26.03,0,.07,0,.1.04.17.17.23.4.3.62.15.46.19.97.11,1.46-.02.1-.02.18-.1.26-.01.01-.02.05-.05.05-.03,0-.07-.03-.07-.06Z"/>
    </g>
  </g></svg>
              </div>
              <input type="text" class="block w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-[#f4f4f6]/20 rounded-xl text-sm text-gray-900 dark:text-[#f4f4f6] placeholder-gray-500 dark:placeholder-[#A8A29E] focus:border-[#d2691e] focus:ring-[#d2691e] outline-none transition-colors" placeholder="Search characters...">
            </div>
          </div>
          
          <!-- Character List -->
          <div class="flex-1 overflow-y-auto scrollbar-hide p-3 space-y-1">
            
            <!-- Item 1 (Active) -->
            <div class="group flex items-center justify-between p-2 rounded-xl bg-[#d2691e]/10 dark:bg-[#d2691e]/10 border border-[#d2691e]/30 dark:border-[#d2691e]/30 cursor-pointer" onclick="showDetailView()" onmouseleave="resetDelete(this)">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-200 dark:bg-[#292524] flex items-center justify-center shrink-0 overflow-hidden">
                  <svg class="w-5 h-5 text-gray-400 dark:text-[#A8A29E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                  <h3 class="font-newsreader text-sm font-semibold text-gray-900 dark:text-[#f4f4f6]">Tiny Buddy</h3>
                  <p class="text-[11px] text-gray-500 dark:text-[#A8A29E]">Official Mascot</p>
                </div>
              </div>
              <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1">
                <button class="p-1.5 text-gray-500 hover:text-[#d2691e] transition-colors rounded-md hover:bg-white dark:hover:bg-[#1c1c1c] shadow-sm" title="Edit">
                  <svg class="w-3.5 h-3.5" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 20.52 20.25"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <g id="pencil">
    <g>
      <path class="cls-1" d="M.44,18.39l.52-1.32.73-1.72.56-1.23s.04-.04.06-.05.05,0,.05.03l-.07.39c.13-.27.28-.5.12-.64l.53-.53h-.15s.94-.93.94-.93l1.46-1.54.65-.68c.38-.4.84-.59,1.16-1.06l-.32.24c.07-.1.2-.27.32-.32.14-.05.13-.12.22-.21l1.27-1.21c.13-.12.24-.21.36-.34l.5-.52,1.03-.95c.39-.44.84-.79,1.26-1.2l.84-.82c.09-.09.19-.15.15-.29.08-.03.09-.12.13-.18.13-.23.32-.42.51-.6l.6-.58c.17-.16.38-.27.59-.37l.34.11c0-.14.06-.26.13-.38.41-.65,1.07-1.38,1.84-1.51.47-.08.96.02,1.38.27.17.1.35.2.51.33l1,.83c.57.47.99,1.33.86,2-.15.75-.88,1.6-1.54,1.97-.18.1-.34.2-.49.34.08.21-.01.4-.15.55l-.21.23c-.38.43-.81.8-1.32,1.07-.04,0-.14-.02-.17,0l-.46.56c-.41.5-.8.99-1.25,1.47l-.46.5-1.2,1.21c-.26.27-.51.54-.82.77-.08.06-.14.16-.24.21-.04.02-.09.13-.14.17l-.94.8-.36.34c-.34.32-.69.64-1,1-.35.41-.71.78-1.11,1.14l-.55.52-.87.84c-.11.1-.12.12-.26.14-.16.02-.27.28-.37.36-.12-.05-.18-.03-.28.04-.62.41-1.3.68-1.96,1-.42.21-.83.33-1.27.49l-1.6.59-.93.41c-.22.1-.51,0-.56-.22-.05-.24.02-.44.09-.65l.33-.95ZM20.08,2.83c-.05-.37-.23-.67-.5-.89.19.3.36.59.5.89ZM17.9,4.82l.49.59.56-.71c.24-.24.44-.47.59-.78.12-.25.28-.53.26-.83s-.2-.57-.37-.8l-.56-.72c-.31-.4-.76-.67-1.2-.91-.3-.16-.61-.15-.92-.04-.75.28-1.09.87-1.36.9-.08,0-.12-.04-.19.06-.09.13-.21.26-.36.33.59.39,1.14.81,1.65,1.3.55.53.95,1.03,1.42,1.6ZM20.02,3.79c.2-.21.19-.46.18-.72-.02,0-.05-.01-.09,0,.05.25-.05.48-.09.71ZM16.34,3.58l-.62-.71-.53-.38c.38.4.74.78,1.15,1.09ZM16.65,7.21c.15-.06.17-.17.24-.25.41-.45.83-.86,1.3-1.25l-1.14-.99c-.08-.07-.12-.16-.23-.19-.8-.73-1.55-1.48-2.2-2.35l-.1-.18-1.09,1.25-.39.44.59.42.89.77c.28.25.52.5.78.76.49.5.94,1.01,1.35,1.58ZM7.04,16.72c.2-.25.34-.54.57-.73l.23-.26.8-.8,1.25-1.17.7-.67c.54-.52,1.02-1.07,1.53-1.62.16-.17.32-.31.47-.5.31-.38.6-.74.95-1.06l.74-.68.43-.43c.14-.14.28-.26.43-.39l.23-.2.65-.55c.08-.12.18-.19.35-.24l-1.02-.94-1.28,1.43-.29.33c-.51.64-1.1,1.19-1.68,1.77l-.45.45-1.7,1.69-.72.74c-.15.15-.31.29-.45.45-.22.25-.45.45-.68.69l-1.61,1.71c-.13.14-.28.22-.41.34l-.47.45c.44.07.9.25,1,.65l.39-.47ZM5.38,16.33l.33-.31.74-.81.53-.58.71-.78,1.98-1.93c-.04.15-.16.2-.18.33.17-.14.33-.26.44-.42.1-.14.31-.25.5-.48.34-.41.74-.73,1.09-1.11.24-.26.49-.48.74-.73l.69-.69c.13-.13.25-.26.38-.4l.96-1.06.55-.57c.14-.15.28-.27.45-.39-.57-.45-1.06-.91-1.53-1.45l-.46.52-.63.66-1.25,1.35-.57.55-1.14,1.15-1.06,1.05-.5.48-.15-.04-1.4,1.45-.72.8-1.63,1.69-.5.49c.33,0,.65.02.95.16l.26.26c.27.19.39.46.4.8ZM3.15,13.61c-.09.05-.22.03-.27.19.52.05.81.56.87,1.11.38-.43.75-.82,1.14-1.22l.57-.6,1.02-1.12,2.14-2.18,1.12-1.17.75-.78.56-.58.69-.67.87-.81.59-.58.4-.37-.85-.85-.86.97-.7.81-2.01,2.13c-.16.18-.33.31-.5.47l-.46.43c-.25.32-.74.74-1.11,1.11l-.48.48-.89.8c-.07-.02-.01-.1-.03-.12-.03-.04-.08.02-.08.05,0,.03-.02.07.08.07-.03.14-.2.11-.28.13l-.65.64-1.1,1.12c-.18.18-.3.4-.54.54ZM13.1,10.94c.07.08.13,0,.11-.06.54-.44,1.03-.91,1.46-1.46l.23-.25s-.03,0-.04-.02l-.98.97c-.14.13-.25.24-.38.38l-.4.45ZM10.15,8.6c.22-.23.4-.41.6-.62l.27-.27c.36-.35.67-.73,1.05-1.07-.03-.03-.07-.02-.1-.01,0,.13-.14.14-.2.2l-1.49,1.51-.4.47c.12-.04.19-.13.27-.22ZM9.05,9.73l.21-.2.48-.52-.04-.04-.26.28-.71.74c.17,0,.22-.17.32-.26ZM1.74,16.36c-.09.42-.26.8-.46,1.18-.08.14-.17.26-.19.43.52.14,1.12.56,1.29,1.06l.73-.33c.22-.1.4-.26.64-.36l1.12-.44c.37-.15.73-.26,1.13-.35.11-.03.21-.13.35-.04.02-.08,0-.15-.07-.19-.3-.21-.66-.34-1.04-.3-.1.01-.32.12-.36-.05l-.06-.29c-.04-.2.07-.42,0-.6-.19-.41-.53-.64-.96-.65-.15,0-.27,0-.37.14l-.03-.13s-.04-.01-.08,0c.07-.09-.03-.17-.02-.23.06-.34-.08-.65-.26-.92-.12-.19-.46-.31-.48-.21-.1.62-.57,1.68-.88,2.3Z"/>
      <path class="cls-1" d="M16.5,5.36c-.09-.01-.19-.07-.24-.17-.02-.04-.14-.06-.14-.14.01-.29-.26-.17-.44-.5-.09-.16-.28-.21-.41-.32-.09-.08-.11-.23-.3-.21.02-.06.05-.06.11-.04-.02-.2-.25-.14-.3-.41,0-.01-.05,0-.05,0-.02.02-.05-.05-.02-.06.08-.04.16.05.22.1l.41.31c.01-.1-.03-.18-.08-.23l-.12-.02s-.01-.09,0-.15c0,0-.1.07-.13.05-.32-.33-.65-.61-1-.92.29-.05.41.2.58.33.15.12.31.22.45.36.03.03.11.03.1.11.3.17.55.47.83.68.1.08.19.14.23.27.08.02.2.06.27.14l.52.56c.23.24.48.46.6.82h-.2c.04.08.08.11.09.22l-.35-.2.04.12-.28-.21c.12.21.21.39.3.63.02.05-.07.07-.12.08-.06,0-.09-.08-.11-.11-.07-.01-.09.08-.08.13,0,.04.09.05.08.16-.2-.09-.33-.23-.48-.37l-.67-.62c.09-.15.24.06.31.03.21-.09-.55-.3-.36-.52.01-.01.3.04.41.27.03,0,.07-.01.08,0,0,.02,0,.07,0,.11.21.03.42.12.45.33.08-.06.03-.13.02-.18-.02-.07-.08-.09-.16-.11,0-.1,0-.2-.04-.32Z"/>
      <path class="cls-1" d="M14.59,3.42l-.32-.31c-.07-.07-.2-.08-.22-.23l.39.25s.3.3.29.31-.04.03-.06.04c-.04.02-.06-.02-.08-.07Z"/>
      <path class="cls-1" d="M15.69,5.15c.05.11-.05.15-.12.16-.08.01-.12-.06-.14-.11-.04-.08.08-.2.15-.17.03.01,0,.06,0,.08,0,.02.09.02.1.04Z"/>
      <path class="cls-1" d="M14.95,4.78s-.02.08-.04.08c-.03,0-.23-.15-.19-.27.1.03.17.09.22.19Z"/>
    </g>
  </g></svg>
                </button>
                <div class="relative flex items-center inline-delete-container">
                  <button type="button" onclick="showInlineConfirm(this, event)" class="p-1.5 text-gray-500 hover:text-red-500 transition-colors rounded-md hover:bg-white dark:hover:bg-[#1c1c1c] shadow-sm delete-trigger" title="Delete">
                    <svg class="w-4 h-4" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 24 24"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <g id="trash">
    <g>
      <path class="cls-1" d="M3.42,5.43h2.07s1.53,0,1.53,0h1.26c.78,0,1.54.09,2.32.07h.25s2.97-.09,2.97-.09c.87-.02,1.71-.05,2.57.1.14.02.25.12.43.05l-.36-.62c-.21-.36-.66-.56-.7-.63-.07-.13-.26-.26-.39-.34-.03,0-.11.05-.13.03l-.08-.08c-.2-.12-.39-.2-.61-.25l-.82-.2c-.17-.04-.34-.04-.53-.08l-.55-.11-.94-.16c-.57-.09-1.13-.22-1.71-.23-.09,0-.16-.01-.24-.01l-1.29-.07c-.76-.04-2.1.18-2.92.35l-.78.16-1.91.53c-.49.14-.94.31-1.39.56-.17.09-.31.24-.46.37-.32.28-.45.85-.54.86-.12.02-.1-.16-.09-.4-.34.48-.02.68-.11.75-.13.12-.44-.33-.18-.88.51-1.12,1.41-1.57,2.52-2s2.13-.65,3.3-.79c-.09-.34.1-.83.32-1.1.4-.5.84-.73,1.4-.96.79-.32,1.6-.32,2.4-.08.69.21,1.22.67,1.5,1.34.1.24.18.55.08.81l1.08.2.87.14.34.09c.08.02.16.02.25.04l.52.15s0,.06.02.05l.09-.03c.6.14,1.3.59,1.78.99l-.03.07s0,.03.02.04c.03.01.05-.01.05-.05.13.02.14.17.2.24.2.22.35.42.47.69.13.3.25.6.2.95-.03.04-.1.08-.08.17-.07,0-.18.01-.23-.04-.21-.21-.26.01-.57.05-.65.08-1.28.09-1.93.1-.5.01-.98.03-1.48,0h-1.17s-1.38-.07-1.38-.07h-.76c-.25,0-.49.01-.75.02h-.91s-2.03.1-2.03.1l-1.44.02-1.06.02-.84-.03c-.57-.02-2.4-.09-2.46-.3,0-.02.04-.09.07-.08.63.09,1.24.07,1.89.07.81,0,1.59.01,2.4-.04h.97s0-.07,0-.07l-1.72-.05H1.97c-.35,0-.67.05-1.03-.03.15-.1.31-.11.47-.12l2-.17ZM9.73,2.13c.42,0,.81.05,1.24.11-.16-.17-.18-.4-.31-.58l-.37-.53-.44-.19-.45-.14c-.72-.1-1.49.03-2.13.39-.33.19-.55.52-.73.83-.06.1-.08.15-.17.25.34-.08.65-.07.98-.11.73-.1,1.63-.06,2.38-.01ZM6.18,2.29l.2-.56c-.14.16-.22.34-.2.56ZM11.42,2.29s.05-.04.04-.07c-.06-.15-.06-.31-.16-.45,0,0-.01,0-.03.02l.15.49Z"/>
      <g>
        <path class="cls-1" d="M7.54,25.71l-1.07.02c-.44,0-.86.01-1.3-.02-.35-.03-.69-.02-1.04-.1s-.69-.13-1.02-.28c-.05-.04-.1-.14-.12-.2-.04-.15-.16-.25-.26-.35l-.03-.13s-.06-.04-.12-.03l-.16-.69-.22-1c-.06-.29-.11-.57-.17-.87l-.21-1.15-.05-.24-.12-.61c-.04-.19-.06-.36-.09-.56-.04-.3-.1-.59-.13-.89l-.14-1.4c-.08-.81-.15-1.6-.17-2.41-.01-.53-.05-1.04-.12-1.56-.03-.27-.03-.53-.1-.79-.02-.08.04-.14.06-.19.05-.13-.02-.27-.05-.41-.15-.83-.27-1.64-.35-2.48-.02-.25-.07-.49,0-.76.06.12,0,.24.03.34.01.02.03.03.04.02v-.97c0-.1,0-.17-.12-.2-.03-.28.33-.11.52-.24-.07-.02-.13-.06-.13-.1,0-.04.05-.11.11-.11l.98-.02h.79c.56.02,1.1.01,1.65-.01l2.03-.1.7-.05,1.34-.04c.33,0,.65-.03.98-.05l.61-.03c.82-.04,1.63-.07,2.46-.03l1.91.09.71.05.47.06c.22.03.4.05.61.07l.6.05c.02.09.08.14.07.23l-.27.05c-.05.01-.08.04-.1.06-.04.03,0,.12,0,.17l-.15,2.23.2-1.21c.06-.37.11-.73.05-1.12.28.04.21.73.23,1.14.02.45-.05.88-.08,1.33-.02.41-.1.78-.15,1.18l-.09.68-.29,1.63-.1.77-.06,1.16c-.04.8-.05,1.58-.14,2.36l-.09.78-.04.23-.29,1.8c-.07.41-.14.78-.16,1.2l-.06,1.51-.1.49c-.08.42-.35.73-.65,1.03l-.29.29s-.07.04-.11.04c-.03,0-.06-.06-.08-.15-.66.32-1.36.41-2.09.4h-.5s-.88-.03-.88-.03l-1.65.06-.58.04h-.88ZM11.66,7.59l1.06-.06c.07,0,.15.04.18-.07-.45.01-.88-.05-1.37-.03l-1.96.08-.37.04c.12.05.25.04.39.04h.56c.31,0,.6,0,.92.01.18.01.38-.09.59-.02ZM2.86,22.85l.06.3.38,1.41c.45.17.91.26,1.39.3l1.41.1,4.24-.02,2.45-.18c.33-.02.63-.07.95.05-.04.08-.11.14-.18.16l-.51.17c.14.08.26-.01.38-.03l.43-.08c.11-.02.2-.02.28.07.14-.3.12-.58.18-.87l.4-1.77c.09-.41.09-.79.09-1.21.01-1.09.04-2.15.17-3.23l.12-.97.21-1.42c.04-.29.11-.55.16-.84l.08-.47c.06-.34.11-.69.12-1.05.02-.85.09-1.66.22-2.5l.37-2.5c.03-.22.08-.41.15-.61l-.56.12-1.05.07-.7.05c-.69.05-1.36.13-2.06.13l-2.46.02h-.39s-2.19-.11-2.19-.11l-.96-.07-.97-.02c-.39,0-.76-.02-1.16-.01l-1.04.03-.95.04-1.09-.03.21,1.18c.05-.08.05-.16.13-.17.1.47.16.91.21,1.37l.08.71c0,.38.05.73.1,1.11l.35,2.65.33,2.19.28,2.49.23,2.56.09.87ZM2.02,18.81l.14.93c.04-.16,0-.31-.01-.47l-.13-1.11-.25-1.94c-.06.29,0,.58.03.88.02.34.05.64.1.98l.12.73ZM15.54,18.11l.04-.39c.01-.13.06-.24,0-.37l-.07.61-.06.34c.07-.04.09-.11.1-.19ZM14.88,23.63l-.16.69c.13-.2.23-.51.16-.69ZM4.52,25.23c-.51-.1-.98-.18-1.43-.45l-.05.25c.5.08.98.24,1.48.2ZM13.06,25.12l-.71.11c.21.02.57.03.71-.11Z"/>
        <path class="cls-1" d="M8.67,13.73l-1.43-.05-.62-.03-1.8-.17c-.2-.02-.45.01-.6-.1-.08-.06-.08-.2-.05-.26.04-.08.16-.05.25-.06l2.46-.29,1.35-.15c1.09-.12,2.16-.18,3.26-.19.36,0,.69,0,1.04.02.31.02.61.03.87.22l.04.19c0,.19-.25.29-.39.21v.06s-.04.03-.07.04c-1.03.38-2.67.56-3.79.57h-.53ZM7.82,13.29c.17-.05.25-.02.39-.01,1.26.04,2.5-.03,3.75-.18.18-.02.34-.04.49-.13l-2.26.05-.46.03c-.38-.02-.74.07-1.12.1l-.63.06c-.06-.02-.13,0-.14.08Z"/>
        <path class="cls-1" d="M9.93,18.21l-.98.04-2.29-.05-1.1-.08c-.26-.02-.42-.07-.43-.25,0-.07.02-.2.11-.21l.48-.04,2.12-.27c1.21-.15,2.89-.44,3.99-.17.19.05.35.1.48.23-.15.12.08.22-.15.36-.51.32-1.56.4-2.24.42ZM10.77,17.63c.23-.01.42-.06.66-.05.11,0,.25-.11.37-.04.09.05.18.05.27-.02l-.44-.07-.46-.02h-.42s-.51.04-.51.04l-1.16.11c-.22.02-.42.06-.63.09l-.39.05c.55.06,1.07.03,1.61,0l1.1-.07Z"/>
      </g>
    </g>
  </g></svg>
                  </button>
                  <div class="hidden inline-confirm items-center gap-1 bg-red-50 dark:bg-red-500/10 rounded-md px-1 ml-1 absolute right-0">
                    <button type="button" onclick="executeDelete(this, event)" class="p-1 text-red-500 hover:scale-110 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
          </button>
          <button type="button" onclick="cancelDelete(this, event)" class="p-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Item 2 -->
            <div class="group flex items-center justify-between p-2 rounded-xl hover:bg-gray-50 dark:hover:bg-[#6b4e3a]/20 border border-transparent cursor-pointer transition-colors" onclick="showDetailView()" onmouseleave="resetDelete(this)">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-[#111111] flex items-center justify-center shrink-0 border border-gray-200 dark:border-[#f4f4f6]/20">
                  <svg class="w-5 h-5 text-gray-400 dark:text-[#A8A29E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                  <h3 class="font-newsreader text-sm font-semibold text-gray-900 dark:text-[#f4f4f6]">Founder Avatar</h3>
                  <p class="text-[11px] text-gray-500 dark:text-[#A8A29E]">3D Style</p>
                </div>
              </div>
              <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 pr-1">
                <button class="p-1.5 text-gray-500 hover:text-[#d2691e] transition-colors rounded-md hover:bg-white dark:hover:bg-[#1c1c1c] shadow-sm" title="Edit">
                  <svg class="w-3.5 h-3.5" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 20.52 20.25"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <g id="pencil">
    <g>
      <path class="cls-1" d="M.44,18.39l.52-1.32.73-1.72.56-1.23s.04-.04.06-.05.05,0,.05.03l-.07.39c.13-.27.28-.5.12-.64l.53-.53h-.15s.94-.93.94-.93l1.46-1.54.65-.68c.38-.4.84-.59,1.16-1.06l-.32.24c.07-.1.2-.27.32-.32.14-.05.13-.12.22-.21l1.27-1.21c.13-.12.24-.21.36-.34l.5-.52,1.03-.95c.39-.44.84-.79,1.26-1.2l.84-.82c.09-.09.19-.15.15-.29.08-.03.09-.12.13-.18.13-.23.32-.42.51-.6l.6-.58c.17-.16.38-.27.59-.37l.34.11c0-.14.06-.26.13-.38.41-.65,1.07-1.38,1.84-1.51.47-.08.96.02,1.38.27.17.1.35.2.51.33l1,.83c.57.47.99,1.33.86,2-.15.75-.88,1.6-1.54,1.97-.18.1-.34.2-.49.34.08.21-.01.4-.15.55l-.21.23c-.38.43-.81.8-1.32,1.07-.04,0-.14-.02-.17,0l-.46.56c-.41.5-.8.99-1.25,1.47l-.46.5-1.2,1.21c-.26.27-.51.54-.82.77-.08.06-.14.16-.24.21-.04.02-.09.13-.14.17l-.94.8-.36.34c-.34.32-.69.64-1,1-.35.41-.71.78-1.11,1.14l-.55.52-.87.84c-.11.1-.12.12-.26.14-.16.02-.27.28-.37.36-.12-.05-.18-.03-.28.04-.62.41-1.3.68-1.96,1-.42.21-.83.33-1.27.49l-1.6.59-.93.41c-.22.1-.51,0-.56-.22-.05-.24.02-.44.09-.65l.33-.95ZM20.08,2.83c-.05-.37-.23-.67-.5-.89.19.3.36.59.5.89ZM17.9,4.82l.49.59.56-.71c.24-.24.44-.47.59-.78.12-.25.28-.53.26-.83s-.2-.57-.37-.8l-.56-.72c-.31-.4-.76-.67-1.2-.91-.3-.16-.61-.15-.92-.04-.75.28-1.09.87-1.36.9-.08,0-.12-.04-.19.06-.09.13-.21.26-.36.33.59.39,1.14.81,1.65,1.3.55.53.95,1.03,1.42,1.6ZM20.02,3.79c.2-.21.19-.46.18-.72-.02,0-.05-.01-.09,0,.05.25-.05.48-.09.71ZM16.34,3.58l-.62-.71-.53-.38c.38.4.74.78,1.15,1.09ZM16.65,7.21c.15-.06.17-.17.24-.25.41-.45.83-.86,1.3-1.25l-1.14-.99c-.08-.07-.12-.16-.23-.19-.8-.73-1.55-1.48-2.2-2.35l-.1-.18-1.09,1.25-.39.44.59.42.89.77c.28.25.52.5.78.76.49.5.94,1.01,1.35,1.58ZM7.04,16.72c.2-.25.34-.54.57-.73l.23-.26.8-.8,1.25-1.17.7-.67c.54-.52,1.02-1.07,1.53-1.62.16-.17.32-.31.47-.5.31-.38.6-.74.95-1.06l.74-.68.43-.43c.14-.14.28-.26.43-.39l.23-.2.65-.55c.08-.12.18-.19.35-.24l-1.02-.94-1.28,1.43-.29.33c-.51.64-1.1,1.19-1.68,1.77l-.45.45-1.7,1.69-.72.74c-.15.15-.31.29-.45.45-.22.25-.45.45-.68.69l-1.61,1.71c-.13.14-.28.22-.41.34l-.47.45c.44.07.9.25,1,.65l.39-.47ZM5.38,16.33l.33-.31.74-.81.53-.58.71-.78,1.98-1.93c-.04.15-.16.2-.18.33.17-.14.33-.26.44-.42.1-.14.31-.25.5-.48.34-.41.74-.73,1.09-1.11.24-.26.49-.48.74-.73l.69-.69c.13-.13.25-.26.38-.4l.96-1.06.55-.57c.14-.15.28-.27.45-.39-.57-.45-1.06-.91-1.53-1.45l-.46.52-.63.66-1.25,1.35-.57.55-1.14,1.15-1.06,1.05-.5.48-.15-.04-1.4,1.45-.72.8-1.63,1.69-.5.49c.33,0,.65.02.95.16l.26.26c.27.19.39.46.4.8ZM3.15,13.61c-.09.05-.22.03-.27.19.52.05.81.56.87,1.11.38-.43.75-.82,1.14-1.22l.57-.6,1.02-1.12,2.14-2.18,1.12-1.17.75-.78.56-.58.69-.67.87-.81.59-.58.4-.37-.85-.85-.86.97-.7.81-2.01,2.13c-.16.18-.33.31-.5.47l-.46.43c-.25.32-.74.74-1.11,1.11l-.48.48-.89.8c-.07-.02-.01-.1-.03-.12-.03-.04-.08.02-.08.05,0,.03-.02.07.08.07-.03.14-.2.11-.28.13l-.65.64-1.1,1.12c-.18.18-.3.4-.54.54ZM13.1,10.94c.07.08.13,0,.11-.06.54-.44,1.03-.91,1.46-1.46l.23-.25s-.03,0-.04-.02l-.98.97c-.14.13-.25.24-.38.38l-.4.45ZM10.15,8.6c.22-.23.4-.41.6-.62l.27-.27c.36-.35.67-.73,1.05-1.07-.03-.03-.07-.02-.1-.01,0,.13-.14.14-.2.2l-1.49,1.51-.4.47c.12-.04.19-.13.27-.22ZM9.05,9.73l.21-.2.48-.52-.04-.04-.26.28-.71.74c.17,0,.22-.17.32-.26ZM1.74,16.36c-.09.42-.26.8-.46,1.18-.08.14-.17.26-.19.43.52.14,1.12.56,1.29,1.06l.73-.33c.22-.1.4-.26.64-.36l1.12-.44c.37-.15.73-.26,1.13-.35.11-.03.21-.13.35-.04.02-.08,0-.15-.07-.19-.3-.21-.66-.34-1.04-.3-.1.01-.32.12-.36-.05l-.06-.29c-.04-.2.07-.42,0-.6-.19-.41-.53-.64-.96-.65-.15,0-.27,0-.37.14l-.03-.13s-.04-.01-.08,0c.07-.09-.03-.17-.02-.23.06-.34-.08-.65-.26-.92-.12-.19-.46-.31-.48-.21-.1.62-.57,1.68-.88,2.3Z"/>
      <path class="cls-1" d="M16.5,5.36c-.09-.01-.19-.07-.24-.17-.02-.04-.14-.06-.14-.14.01-.29-.26-.17-.44-.5-.09-.16-.28-.21-.41-.32-.09-.08-.11-.23-.3-.21.02-.06.05-.06.11-.04-.02-.2-.25-.14-.3-.41,0-.01-.05,0-.05,0-.02.02-.05-.05-.02-.06.08-.04.16.05.22.1l.41.31c.01-.1-.03-.18-.08-.23l-.12-.02s-.01-.09,0-.15c0,0-.1.07-.13.05-.32-.33-.65-.61-1-.92.29-.05.41.2.58.33.15.12.31.22.45.36.03.03.11.03.1.11.3.17.55.47.83.68.1.08.19.14.23.27.08.02.2.06.27.14l.52.56c.23.24.48.46.6.82h-.2c.04.08.08.11.09.22l-.35-.2.04.12-.28-.21c.12.21.21.39.3.63.02.05-.07.07-.12.08-.06,0-.09-.08-.11-.11-.07-.01-.09.08-.08.13,0,.04.09.05.08.16-.2-.09-.33-.23-.48-.37l-.67-.62c.09-.15.24.06.31.03.21-.09-.55-.3-.36-.52.01-.01.3.04.41.27.03,0,.07-.01.08,0,0,.02,0,.07,0,.11.21.03.42.12.45.33.08-.06.03-.13.02-.18-.02-.07-.08-.09-.16-.11,0-.1,0-.2-.04-.32Z"/>
      <path class="cls-1" d="M14.59,3.42l-.32-.31c-.07-.07-.2-.08-.22-.23l.39.25s.3.3.29.31-.04.03-.06.04c-.04.02-.06-.02-.08-.07Z"/>
      <path class="cls-1" d="M15.69,5.15c.05.11-.05.15-.12.16-.08.01-.12-.06-.14-.11-.04-.08.08-.2.15-.17.03.01,0,.06,0,.08,0,.02.09.02.1.04Z"/>
      <path class="cls-1" d="M14.95,4.78s-.02.08-.04.08c-.03,0-.23-.15-.19-.27.1.03.17.09.22.19Z"/>
    </g>
  </g></svg>
                </button>
                <div class="relative flex items-center inline-delete-container">
                  <button type="button" onclick="showInlineConfirm(this, event)" class="p-1.5 text-gray-500 hover:text-red-500 transition-colors rounded-md hover:bg-white dark:hover:bg-[#1c1c1c] shadow-sm delete-trigger" title="Delete">
                    <svg class="w-4 h-4" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 24 24"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <g id="trash">
    <g>
      <path class="cls-1" d="M3.42,5.43h2.07s1.53,0,1.53,0h1.26c.78,0,1.54.09,2.32.07h.25s2.97-.09,2.97-.09c.87-.02,1.71-.05,2.57.1.14.02.25.12.43.05l-.36-.62c-.21-.36-.66-.56-.7-.63-.07-.13-.26-.26-.39-.34-.03,0-.11.05-.13.03l-.08-.08c-.2-.12-.39-.2-.61-.25l-.82-.2c-.17-.04-.34-.04-.53-.08l-.55-.11-.94-.16c-.57-.09-1.13-.22-1.71-.23-.09,0-.16-.01-.24-.01l-1.29-.07c-.76-.04-2.1.18-2.92.35l-.78.16-1.91.53c-.49.14-.94.31-1.39.56-.17.09-.31.24-.46.37-.32.28-.45.85-.54.86-.12.02-.1-.16-.09-.4-.34.48-.02.68-.11.75-.13.12-.44-.33-.18-.88.51-1.12,1.41-1.57,2.52-2s2.13-.65,3.3-.79c-.09-.34.1-.83.32-1.1.4-.5.84-.73,1.4-.96.79-.32,1.6-.32,2.4-.08.69.21,1.22.67,1.5,1.34.1.24.18.55.08.81l1.08.2.87.14.34.09c.08.02.16.02.25.04l.52.15s0,.06.02.05l.09-.03c.6.14,1.3.59,1.78.99l-.03.07s0,.03.02.04c.03.01.05-.01.05-.05.13.02.14.17.2.24.2.22.35.42.47.69.13.3.25.6.2.95-.03.04-.1.08-.08.17-.07,0-.18.01-.23-.04-.21-.21-.26.01-.57.05-.65.08-1.28.09-1.93.1-.5.01-.98.03-1.48,0h-1.17s-1.38-.07-1.38-.07h-.76c-.25,0-.49.01-.75.02h-.91s-2.03.1-2.03.1l-1.44.02-1.06.02-.84-.03c-.57-.02-2.4-.09-2.46-.3,0-.02.04-.09.07-.08.63.09,1.24.07,1.89.07.81,0,1.59.01,2.4-.04h.97s0-.07,0-.07l-1.72-.05H1.97c-.35,0-.67.05-1.03-.03.15-.1.31-.11.47-.12l2-.17ZM9.73,2.13c.42,0,.81.05,1.24.11-.16-.17-.18-.4-.31-.58l-.37-.53-.44-.19-.45-.14c-.72-.1-1.49.03-2.13.39-.33.19-.55.52-.73.83-.06.1-.08.15-.17.25.34-.08.65-.07.98-.11.73-.1,1.63-.06,2.38-.01ZM6.18,2.29l.2-.56c-.14.16-.22.34-.2.56ZM11.42,2.29s.05-.04.04-.07c-.06-.15-.06-.31-.16-.45,0,0-.01,0-.03.02l.15.49Z"/>
      <g>
        <path class="cls-1" d="M7.54,25.71l-1.07.02c-.44,0-.86.01-1.3-.02-.35-.03-.69-.02-1.04-.1s-.69-.13-1.02-.28c-.05-.04-.1-.14-.12-.2-.04-.15-.16-.25-.26-.35l-.03-.13s-.06-.04-.12-.03l-.16-.69-.22-1c-.06-.29-.11-.57-.17-.87l-.21-1.15-.05-.24-.12-.61c-.04-.19-.06-.36-.09-.56-.04-.3-.1-.59-.13-.89l-.14-1.4c-.08-.81-.15-1.6-.17-2.41-.01-.53-.05-1.04-.12-1.56-.03-.27-.03-.53-.1-.79-.02-.08.04-.14.06-.19.05-.13-.02-.27-.05-.41-.15-.83-.27-1.64-.35-2.48-.02-.25-.07-.49,0-.76.06.12,0,.24.03.34.01.02.03.03.04.02v-.97c0-.1,0-.17-.12-.2-.03-.28.33-.11.52-.24-.07-.02-.13-.06-.13-.1,0-.04.05-.11.11-.11l.98-.02h.79c.56.02,1.1.01,1.65-.01l2.03-.1.7-.05,1.34-.04c.33,0,.65-.03.98-.05l.61-.03c.82-.04,1.63-.07,2.46-.03l1.91.09.71.05.47.06c.22.03.4.05.61.07l.6.05c.02.09.08.14.07.23l-.27.05c-.05.01-.08.04-.1.06-.04.03,0,.12,0,.17l-.15,2.23.2-1.21c.06-.37.11-.73.05-1.12.28.04.21.73.23,1.14.02.45-.05.88-.08,1.33-.02.41-.1.78-.15,1.18l-.09.68-.29,1.63-.1.77-.06,1.16c-.04.8-.05,1.58-.14,2.36l-.09.78-.04.23-.29,1.8c-.07.41-.14.78-.16,1.2l-.06,1.51-.1.49c-.08.42-.35.73-.65,1.03l-.29.29s-.07.04-.11.04c-.03,0-.06-.06-.08-.15-.66.32-1.36.41-2.09.4h-.5s-.88-.03-.88-.03l-1.65.06-.58.04h-.88ZM11.66,7.59l1.06-.06c.07,0,.15.04.18-.07-.45.01-.88-.05-1.37-.03l-1.96.08-.37.04c.12.05.25.04.39.04h.56c.31,0,.6,0,.92.01.18.01.38-.09.59-.02ZM2.86,22.85l.06.3.38,1.41c.45.17.91.26,1.39.3l1.41.1,4.24-.02,2.45-.18c.33-.02.63-.07.95.05-.04.08-.11.14-.18.16l-.51.17c.14.08.26-.01.38-.03l.43-.08c.11-.02.2-.02.28.07.14-.3.12-.58.18-.87l.4-1.77c.09-.41.09-.79.09-1.21.01-1.09.04-2.15.17-3.23l.12-.97.21-1.42c.04-.29.11-.55.16-.84l.08-.47c.06-.34.11-.69.12-1.05.02-.85.09-1.66.22-2.5l.37-2.5c.03-.22.08-.41.15-.61l-.56.12-1.05.07-.7.05c-.69.05-1.36.13-2.06.13l-2.46.02h-.39s-2.19-.11-2.19-.11l-.96-.07-.97-.02c-.39,0-.76-.02-1.16-.01l-1.04.03-.95.04-1.09-.03.21,1.18c.05-.08.05-.16.13-.17.1.47.16.91.21,1.37l.08.71c0,.38.05.73.1,1.11l.35,2.65.33,2.19.28,2.49.23,2.56.09.87ZM2.02,18.81l.14.93c.04-.16,0-.31-.01-.47l-.13-1.11-.25-1.94c-.06.29,0,.58.03.88.02.34.05.64.1.98l.12.73ZM15.54,18.11l.04-.39c.01-.13.06-.24,0-.37l-.07.61-.06.34c.07-.04.09-.11.1-.19ZM14.88,23.63l-.16.69c.13-.2.23-.51.16-.69ZM4.52,25.23c-.51-.1-.98-.18-1.43-.45l-.05.25c.5.08.98.24,1.48.2ZM13.06,25.12l-.71.11c.21.02.57.03.71-.11Z"/>
        <path class="cls-1" d="M8.67,13.73l-1.43-.05-.62-.03-1.8-.17c-.2-.02-.45.01-.6-.1-.08-.06-.08-.2-.05-.26.04-.08.16-.05.25-.06l2.46-.29,1.35-.15c1.09-.12,2.16-.18,3.26-.19.36,0,.69,0,1.04.02.31.02.61.03.87.22l.04.19c0,.19-.25.29-.39.21v.06s-.04.03-.07.04c-1.03.38-2.67.56-3.79.57h-.53ZM7.82,13.29c.17-.05.25-.02.39-.01,1.26.04,2.5-.03,3.75-.18.18-.02.34-.04.49-.13l-2.26.05-.46.03c-.38-.02-.74.07-1.12.1l-.63.06c-.06-.02-.13,0-.14.08Z"/>
        <path class="cls-1" d="M9.93,18.21l-.98.04-2.29-.05-1.1-.08c-.26-.02-.42-.07-.43-.25,0-.07.02-.2.11-.21l.48-.04,2.12-.27c1.21-.15,2.89-.44,3.99-.17.19.05.35.1.48.23-.15.12.08.22-.15.36-.51.32-1.56.4-2.24.42ZM10.77,17.63c.23-.01.42-.06.66-.05.11,0,.25-.11.37-.04.09.05.18.05.27-.02l-.44-.07-.46-.02h-.42s-.51.04-.51.04l-1.16.11c-.22.02-.42.06-.63.09l-.39.05c.55.06,1.07.03,1.61,0l1.1-.07Z"/>
      </g>
    </g>
  </g></svg>
                  </button>
                  <div class="hidden inline-confirm items-center gap-1 bg-red-50 dark:bg-red-500/10 rounded-md px-1 ml-1 absolute right-0">
                    <button type="button" onclick="executeDelete(this, event)" class="p-1 text-red-500 hover:scale-110 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
          </button>
          <button type="button" onclick="cancelDelete(this, event)" class="p-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
                  </div>
                </div>
              </div>
            </div>
            
          </div>
        </div>
```

### Input Textarea With Help

```html
<div class="text-field-wrapper space-y-2 relative">
                    <label class="field-label flex justify-between items-center w-full text-sm font-semibold text-gray-900 dark:text-[#f4f4f6] mb-2 transition-colors">
                      <span>Character Name</span>
                      <span class="group relative flex items-center justify-center cursor-help text-gray-400 hover:text-[#d2691e] transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <div class="absolute bottom-full mb-2 right-0 w-56 p-2 bg-gray-900 dark:bg-black text-white text-[11px] font-normal rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 text-center leading-relaxed">
                          Enter the full name or primary alias for this character.
                        </div>
                      </span>
                    </label>
                    <input type="text" id="char-name" value="Tiny Buddy" class="editable-text-input py-3 px-4 block w-full bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-[#f4f4f6]/20 rounded-xl text-sm text-gray-900 dark:text-[#f4f4f6] transition-all duration-300" placeholder="e.g., Tiny Buddy">
                  </div>
```

### Input Textarea

```html
<div class="text-field-wrapper space-y-2 relative">
                    <label class="field-label block text-sm font-semibold text-gray-900 dark:text-[#f4f4f6] mb-2 transition-colors">
                      Character Name
                    </label>
                    <input type="text" id="char-name" value="Tiny Buddy" class="editable-text-input py-3 px-4 block w-full bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-[#f4f4f6]/20 rounded-xl text-sm text-gray-900 dark:text-[#f4f4f6] transition-all duration-300" placeholder="e.g., Tiny Buddy">
                  </div>
```

### Dropdown Select

```html
<div class="field-wrapper space-y-2 relative">
                    <label class="field-label flex justify-between items-center w-full text-sm font-semibold text-gray-900 dark:text-[#f4f4f6] mb-2 transition-colors">
                      Aesthetic Style
                      <span class="group relative flex items-center justify-center cursor-help text-gray-400 hover:text-[#d2691e] transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <div class="absolute bottom-full mb-2 right-0 w-56 p-2 bg-gray-900 dark:bg-black text-white text-[#f4f4f6] text-[11px] font-normal rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 text-center leading-relaxed">
                          Define the rendering engine style used by the AI to generate this character.
                        </div>
                      </span>
                    </label>
                    <select class="py-3 px-4 block w-full bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-[#f4f4f6]/20 rounded-xl text-sm text-gray-900 dark:text-[#f4f4f6] focus:border-[#d2691e] focus:ring-0 outline-none transition-colors appearance-none cursor-pointer">
                      <option value="3d-pixar" selected>3D Pixar Animation</option>
                      <option value="2d-vector">Minimalist 2D Vector</option>
                      <option value="3d-photo">Photorealistic 3D</option>
                      <option value="anime">Anime / Manga Style</option>
                    </select>
                  </div>
```

### Buttons

```html
<button type="button" id="conduct-research-btn" class="group relative py-4 px-20 inline-flex justify-center items-center gap-3 rounded-2xl font-bold bg-white dark:bg-[#1c1a19] border border-gray-200 dark:border-[#44403C] text-gray-900 dark:text-[#f4f4f6] hover:bg-[#d2691e] hover:border-[#d2691e] hover:text-white dark:hover:bg-[#d2691e] dark:hover:border-[#d2691e] dark:hover:text-white focus:outline-none transition-all duration-300 hover:shadow-[0_0_30px_rgba(255,85,0,0.4)] active:scale-95 active:shadow-[0_0_10px_rgba(255,85,0,0.2)] text-base">
        <!-- Search Icon -->
        <svg id="research-icon-search" class="w-7 h-7 shrink-0 group-hover:rotate-12 transition-transform duration-300" viewBox="0 0 24.31 24.49" fill="currentColor">
  <defs>
    
  </defs>
  <g id="research">
    <g>
      <path  d="M10.17,17.46c-.29-.06-.5-.22-.65-.45-.03-.05-.13.02-.17.02l-.75.08c-.44.05-.87-.03-1.31-.03h-.92s-.47,0-.47,0l-.8.02c-.27,0-.53.06-.8.06l-.82.02c-.45.01-.87.07-1.32.04l-.79.02c-.26,0-.54.05-.79-.04-.08-.03-.12-.08-.21-.08-.28.01-.29-1.02-.27-1.46.02-.52,0-1.01-.03-1.53-.05-.75-.03-1.49-.01-2.25v-.6s.06-1.58.06-1.58c.01-.36-.02-.79-.02-1.21v-.54c-.01-.37-.02-.72-.03-1.09l-.02-.98-.03-1.49.08-1.4-.03-.9c0-.11.01-.19.02-.29l.03-.39c.01-.2.14-.29.32-.32.25-.05.51-.11.75-.02.09-.07.11-.17.17-.26.02.05-.02.08,0,.09.03,0,.05-.04.06-.05.21-.19.06-.37.2-.47.05.07,0,.17.07.22,0-.07-.02-.24.07-.25.08-.01.16-.03.25-.05.54-.12,1.09-.12,1.65-.16.22-.02.42,0,.64,0h1.05s.3,0,.3,0l.82.06.96.05c.11,0,.25-.02.34.04h.06s0,.02.02.05c.08-.09.16-.02.22,0,.08,0,.15,0,.23.03l.42.1c.25.06.47.12.72.23.14.06.32.22.48.19l.8-.13h.2c.5-.04,1,0,1.49.07l.27-.13c1.21-.43,2.27-.55,3.54-.61l.87-.04c.91-.04,1.73-.09,2.66.08l.59.1s.08.03.09.05c.01.02-.02.06-.01.09l.07.46c.03-.17.02-.32.09-.47.11.07-.02.26.12.23l.29.37.85.09c.07,0,.17.1.21.14.08.1.06.23.08.34.05.28.05.53.04.81l-.02.58c-.02.38.02.74.03,1.12l.03.91.02.84.03.51v.66s-.02,1.04-.02,1.04l-.09,1.66c-.05.88-.03,1.73,0,2.6l.06,1.27-.02.38c.03.43,0,.83,0,1.26v.37s0,.73,0,.73l-.08.46-.1.22s-.06.07-.08.06c-.04-.01-.06-.05-.04-.12-.16.05-.31.05-.47.06l-.22.02c.13.25.29.48.48.7.07.08.1.18.16.27l.26.42s0,.03-.01.04c-.03,0-.03-.04-.06-.06-.03.09.02.14.07.19,0,.34-.13.69-.35.96-.27.33-1.01.69-1.43.52,0-.02.02-.06,0-.07-.01-.01-.06-.01-.09.01-.08-.12-.19-.21-.33-.26-.05-.15-.18-.24-.33-.31l-.62-.7-.39-.46-.35-.43-.39-.54-.27-.35-.67.05c.11.06.22.04.35.07-.04.07-.13.01-.18.02-.14.03-.24,0-.37,0h-1.1s-1.11-.01-1.11-.01h-.98s-.42,0-.42,0h-.37c-.17.23-.42.34-.7.4-.6.13-1.18.17-1.8.04ZM19.02.3c-.45-.09-.87-.06-1.31-.04.15.09.31.05.47.05.29,0,.55.05.85-.01ZM4.73.37c-.28-.03-.57-.03-.81-.03h-.34s-.75.06-.75.06c.47.06.94,0,1.43,0l.48-.02ZM15.32.49c-.2-.03-.38-.02-.56.05.18.05.36-.04.56-.05ZM17.43,12.52l.56.63.29.34c.24.28.45.55.68.83l.67.84.34.02-.31-.15s.1-.05.15-.04l.67.11c-.1-.35-.1-.69-.12-1.05l-.06-1.24v-.81s.01-.53.01-.53l.02-1.28c0-.73.01-1.44-.07-2.16-.11-.91-.15-1.79-.12-2.7l.07-2.41.03-.96c.01-.34,0-.65-.02-.99l-.02-.45-.32.04c-.07-.03-.1-.02-.18-.02h-.45s-.95.05-.95.05l-.67-.03-1.1-.02c-.1,0-.17-.01-.27-.01h-.24s-.04-.03-.05-.03l-.05.04c-.24,0-.47.02-.71.04l-.91.1c-.2.02-.37.01-.57.07-.25.08-.5.11-.73.23.33.17.64.16,1,.31.44.19.84.42,1.24.69.1.07.18.11.26.19.29.28.56.57.77.92l.53.92.29.53c.3.55.5,1.13.55,1.75,0,.03,0,.07-.01.08-.01.02-.05.03-.08.03.09.05.03.1.04.13l.03.57c.02.36.01.71-.08,1.07-.12.51-.28.99-.49,1.47s-.44.95-.78,1.36c-.09.11-.15.24-.28.34l.45.61c.07.1.13.18.15.31.06-.09.16-.06.21-.08.33-.17.55.25.65.36ZM15.22,14.93l.78.04-.64-.92c-.06-.09-.06-.22-.18-.27-.02-.06,0-.17.02-.23l.08-.32c-.27-.24-.48-.49-.72-.77l-.39.21c-.59.31-1.2.48-1.85.6s-1.26.13-1.91.06c-1.68-.17-3.34-1.33-4.19-2.75l-.62-1.04-.21-.51c-.11-.34-.15-.66-.21-1-.07-.4-.11-.79-.1-1.2,0-.22.03-.42.06-.63.08-.75.4-1.43.77-2.07l.13-.23c.17-.3.28-.61.5-.89.24-.31.54-.54.83-.8.11-.09.22-.16.33-.24.51-.4,1.06-.66,1.69-.89l-.3-.11-.91-.23-.61-.08c-.64-.13-1.27-.09-1.92-.07l-.98.03c-.73.02-1.44.04-2.18,0l-.49-.06s-.07-.05-.11-.01c-.05.04-.02.08-.02.13v.56c0,.5-.07,1-.03,1.51v.52s.06,1.5.06,1.5l.06,2.79c.02,1,.06,1.95.04,2.95l-.05,2.71v1.29s-.03.78-.03.78l.84-.11,1.94-.29c.87-.13,2.05-.21,2.88-.06l.27.05,1.04.21c.22.04.42.07.63.12.63.16,1.1.52,1.56.95l-.06-.4c-.06-.41-.08-.89-.08-1.3,0-.35,0-.69.13-1.01l.06.57c.04-.17,0-.33.1-.51l.04.89-.02,1.71c.34-.23.63-.44.98-.59.99-.44,1.9-.65,2.99-.69.19,0,.36-.03.53.04l-.5.09ZM20.82,8.45l.06,1.08c.06-.27.05-.51.06-.77l.05-1.88c.02-.64-.01-1.26-.03-1.9l-.04-1.14v-.4c-.04-.71-.08-1.4-.06-2.11,0-.13,0-.21-.12-.3-.04-.03-.04-.15-.13-.16v.91s-.02,1.44-.02,1.44l.1,2.3v1.39c0,.15-.02.29.02.42.07-.11.02-.21.02-.3l.03-.94h.04s0,1.59,0,1.59v.36s.03.41.03.41ZM.97,15.53l.04.63.33-.43c.22-.28.36-.28.29-.61-.08.02-.03.1-.06.17-.07-.15-.04-.32-.04-.49v-1.74s-.1-2.69-.1-2.69v-1.62s.04-1.61.04-1.61v-2.25s.06-2.49.06-2.49c0-.29,0-.56,0-.85v-.68c-.18.11-.18.28-.17.46l.04,1.69c.02.76,0,1.48-.06,2.23-.07.97.08,1.94,0,2.76-.05.58-.11,1.14-.1,1.73l.02,1.04.09,2.1v.75s-.03,1.12-.03,1.12c-.01.33-.03.64,0,.95-.07,0-.11-.01-.13,0-.03,0-.03.07-.05.13-.09-.09-.1-.22-.11-.35l-.04-.75-.02.8ZM12.9,12.67l.34-.1-.1.1c.28-.05.5-.19.74-.32.5-.28,1.69-1.31,2.01-1.72l.37-.48c.14-.18.24-.4.31-.62l.3-.89.17-.63c.19-.6.2-1.22.04-1.84l-.19-.75-.08-.43-.11-.33c-.09-.29-.43-.77-.66-1.02l-.32-.34-.2-.19-.61-.5c-.39-.28-.75-.56-1.18-.78-.18-.19-.41-.28-.65-.35-.46-.16-.93-.25-1.41-.28-.14-.01-.22.05-.34.08-.25.06-.51.08-.77.09-.67.04-1.1.15-1.7.42-.4.18-.72.45-1.09.68-.31.19-.51.47-.72.76l-.66.91-.22.35c-.24.39-.34.84-.42,1.3l-.11.66c-.06.34-.14.66-.1,1.01s.13.7.24,1.06c.14.5.29.97.49,1.45.18.42.47.78.79,1.11.22.22.43.44.7.6l.84.48.91.39c.36.15.74.21,1.12.3.32.08.64.1.97.07l.47-.05.36-.06c.16-.03.3-.06.48-.11ZM1.17,5.18l.05-1.07v-.92c-.01-.62-.05-1.24.04-1.88-.05-.04-.1.02-.1.07l-.03.75v2.31c-.01.25,0,.48.04.73ZM21.14,3.54v-.81s-.04-.48-.04-.48l.03-.86s-.08-.04-.13-.03c-.01.72.06,1.41.07,2.12l.02.79.04-.28v-.46ZM20.95,15.1v-.46s-.04.06-.04.08c0,.01-.05-.02-.05-.04.04-.3.04-.59-.01-.88-.08.3-.04.59-.06.89l-.04.53c0,.06-.05.12-.08.17l-.2.02-.71-.14.45.57.42.46c.08.09.09.24.22.27.01-.03.04-.08.06-.07l.08.02.62-.05v-1.11s0-.59,0-.59l-.03-1.18-.02-.77.02-1.04.02-.56.06-.89c.04-.6.08-1.19.03-1.79v-.43s-.04-.46-.04-.46c-.04-.72-.02-1.41,0-2.12l.02-.44.02-1.32c0-.43-.01-.84-.05-1.27v-.49c0-.18,0-.36-.02-.53-.09-.03-.16-.04-.24-.03.02.05.05.07.04.12l-.16-.07.02.58v.51s.03,0,.04-.02l.04-.86c.1.05.05.13.05.21l.03,3.73c-.03,1.34,0,2.64.05,3.98l-.04,1.11-.02.52v3.2s0,.96,0,.96v.41c-.06.04-.13.12-.16.2-.18.04-.41.04-.6.02-.04-.03-.04-.06-.03-.06,0,0,.04-.01.06-.02l.51-.02v-3.1s-.03-.7-.03-.7c-.07,1.04-.09,2.07-.09,3.12,0,.13-.06.26-.03.38.02.06.05.11,0,.21-.22-.18-.56.07-.75-.14l.42-.05c-.15-.03-.3.02-.45-.07.16-.03.31-.04.45-.01l.09.09s.1,0,.1-.05l.03-.54ZM2.15,16.03l1.34-.1,2.1-.25.91-.09c-.42-.06-.83-.05-1.27,0l-2.97.25c-.18.02-.35,0-.52-.08.21-.04.38-.03.57-.05l2.96-.27c.6-.05,2.53.03,3.07.17l1.25.33.51.17c-.18-.14-.4-.19-.61-.28-.25-.1-.49-.19-.76-.24-.09-.02-.18-.04-.25-.08.5.02.94.17,1.39.36s.66.52.87.47c-.02-.14-.11-.22-.23-.27l-.62-.28c-.68-.31-1.46-.59-2.2-.6l-.8-.02c-.71-.02-1.39.05-2.1.14-.97.12-1.91.19-2.89.17.02.07,0,.18-.04.17-.07-.02-.08-.11-.16-.16l-.2.33-.36.42c-.09.11-.16.25-.31.31,0-.13.08-.2.12-.3-.07-.15-.04-.28-.06-.44-.05.13-.04.27-.06.41,0,0-.02-.02-.03-.02v-.57c0-.57.02-1.13,0-1.71l-.04-1.6.04-2.32v-2.15s0-1.57,0-1.57l-.04-.8-.03-.91c-.02-.62-.05-1.31,0-1.9.02-.3.05-.59.02-.89-.01-.12,0-.21.09-.32l-.3.02c.05.23.03.43.03.65v1.24s.02,2.14.02,2.14c0,.64,0,1.25-.03,1.9-.03.73-.03,1.44,0,2.17,0,.11-.03.2,0,.31.02.07.02.21,0,.28-.02.07-.06.11-.05.2.01.19-.04.43.04.61.04.09,0,.1,0,.17l.04,1.15.04.51.02.46.03.56v1.28s.01,1.37.01,1.37l-.03.2.97-.04c.4-.05.79-.09,1.19-.08l1.27.03c.46.01.9,0,1.36.02h.51c.47.03.91-.07,1.36-.1l.6-.04.67-.08c-.11-.04-.2-.05-.31-.06-1.18-.09-2.35-.07-3.53.02l-1.99.16c-.35.03-.69.05-1.03.02-.18-.01-.37.05-.54-.04.37-.08.71-.05,1.07-.07l.6-.04c.17-.01.34-.01.52-.04h-1.9c-.08.01-.16.02-.2-.07l.93-.07h.32s.47-.02.47-.02l2.08-.08.68-.05,1.22-.09c.93-.03,1.83.07,2.75.29-.2-.16-.44-.18-.68-.24-.46-.11-.9-.21-1.37-.24-.61-.04-1.19-.03-1.8.03l-3.09.32c-.42.04-1.31.01-1.29-.08s.51-.01.68-.02ZM1.02,6.01c0-.08.01-.13.01-.2v-.81c0-.09-.02-.16-.02-.26v-.37c0-.09,0-.14,0-.22v-1.56c0-.28.05-.54.05-.82v-.3c-.06.05-.16.08-.2.16-.02.08.04.18.04.27l.05,1.7v.21s.01.22.01.22c0,.17,0,.33,0,.51l-.02,2.35c.03-.1.04-.2.05-.31l.03-.58ZM15.5,2.52c-.17-.23-.38-.38-.61-.51.19.21.41.34.61.51ZM14.84,2.33l-.37-.27-.02.03.69.51c-.05-.14-.18-.19-.3-.27ZM.83,3.96v-.47s-.03.02-.05.02l.02.67c.1-.05.03-.15.03-.21ZM21.37,10.53l.05-.7-.02-.62-.02-.49-.02-.47-.04-1.1v-.95s-.01-.5-.01-.5v-.7s0-.31,0-.31c0-.11.06-.23,0-.33-.07.19-.03.38-.05.58-.03.29-.02.56-.03.85-.03.92-.05,1.81-.04,2.73v1.33s.06.96.06.96l.03.37.02.62c.11-.11.01-.25.03-.34s-.01-.14,0-.23l.04-.71ZM20.4,6.47l.02-.51-.02-1.35-.03.63v.75c0,.17,0,.34.03.48ZM1.16,5.46c0-.08.08-.19-.03-.27l-.02.93s.03-.01.04-.02v-.64ZM5.29,6.51l.2-1c-.13.32-.21.66-.2,1ZM1.09,11v-1.73s.01-1.27.01-1.27c0-.26-.02-.5,0-.75.02-.22.04-.44,0-.66-.08.62-.08,1.22-.08,1.84v.7s.01,2.38.01,2.38l.05-.51ZM1.72,11.94v-.58s0-.6,0-.6l.02-.84v-.9s-.06-2.25-.06-2.25c.03-.04-.02-.07-.05-.02v.8s0,1.21,0,1.21l.03,2.21c0,.13,0,.25,0,.38v.57s.01.05.02.06c0,0,.04-.02.04-.05ZM20.84,12.44v-.54s0-1.44,0-1.44l-.08-1.11-.05-1.37v-.62c-.09.01-.04.1-.05.15-.06.57-.02,1.11-.01,1.68l.03,1.1.1,1.99.02.51c.01-.11.02-.23.02-.34ZM16.81,9.19c.06-.16.14-.3.14-.49-.06.06-.05.13-.07.2-.03.1-.1.17-.07.29ZM21.04,12.64l.05-1.4-.03-1.16s-.03,0-.04-.02v.67s-.04,1.98-.04,1.98c.04,0,.06-.03.06-.07ZM1.18,15.29c.06-.18.02-.36.02-.54v-1.83s0-.6,0-.6l-.03-.72-.04.36c-.02.15.02.3-.04.45.01.03.04.08.04.12-.05.93-.07,1.84.05,2.75ZM16.14,12.5s.05-.05.05-.07c0-.02-.02-.05-.04-.07l-.39-.48c-.07-.08-.09-.18-.19-.24l-.28.25-.33.28c.25.25.45.51.63.83l.54-.49ZM20.55,12.5c0-.09-.03-.16.02-.24l-.06-.36v1.66c0,.1.01.2.05.28v-1.34ZM20.65,19.5l.46-.38c.15-.12.26-.25.38-.4l.1-.06s0-.09,0-.11c.02-.18-.05-.27-.14-.39-.26-.36-.5-.74-.85-1.03.23.37.47.7.61,1.1.03.09.05.17-.01.27-.1-.32-.26-.61-.44-.91l-.17-.29c-.17-.29-.35-.56-.56-.83l-.35-.46c-.04-.06-.07-.13-.09-.18l-.8-1.01c-.5-.57-1.03-1.1-1.45-1.73l-.42-.63c-.07.07-.12.13-.22.11v.07s-.07.01-.08.03l-.63.58s-.07.05-.12.04l-.42.41c.1.07.17.14.22.23.06.12.16.18.24.27l.52.63.75.94.23.29c.32.52.72.97,1.1,1.45.28.35.54.67.88.97l.66.81c.07.08.13.14.19.25.05.09.21.02.29,0l.11-.02ZM10,13.05l-.78-.24c-.06-.02-.12-.1-.21-.05.18.1.37.18.58.22.13.03.25.13.4.07ZM20.83,13.08h-.03v.65h.03v-.65ZM.32,14.55l.04.48v-.94s-.02-.88-.02-.88c-.08.09-.03.2-.03.31s-.02.22-.01.34l.03.68ZM12.27,16.43c1.24-.35,2.68-.45,3.95-.35.09,0,.13,0,.22.03.11.04.28-.03.39.07-.2-.02-.36,0-.51.07l-.22.03-.93-.05h-.29c-.62.05-1.22.08-1.83.19l-.43.08c-.13.02-.27-.04-.4.05,0,.04.1.14.14.1.07-.06.13-.11.23-.11l.88.02h.82s.68-.02.68-.02l1.26-.05h.51c.08.01.11.03.19.04l.31.07-.4-.51-.76-1-.35.03-.27.02c-.8-.03-1.84.15-2.58.43l-.44.21.02.03.43-.16c.7-.18,1.41-.32,2.13-.37.02,0,.07-.08.09-.07l.07.08s.03-.04.02-.05c0,0,.05,0,.06,0l.6.03s.06,0,.06.03c0,.02,0,.05-.02.05,0,0-.04,0-.06,0l-.78.05-1.32.18-.63.13c-.5.14-.96.34-1.43.57-.03-.04,0-.06,0-.12l-.26.16.35.03c.12-.04.21-.12.34-.17.66-.24,1.34-.45,2.05-.52l.78-.02c.29,0,.59.02.87.1.07.02.09.07.17.08.2.01.38.06.57.11-.14,0-.23-.01-.35.01-.21.05-.44-.03-.65-.02h-.89c-.76.08-1.78.17-2.42.51ZM13.6,15.89s.03.03.05.02l.83-.1c.2-.02.37-.04.58-.03-.07-.09-.16-.03-.24-.03-.21,0-.4,0-.61.03l-.61.1ZM15.84,15.76l-.64.02.54.04h.08s.03-.06.02-.06ZM10,16.64l.13-.08s-.08-.07-.13-.09c-.45-.15-.9-.25-1.37-.29l-.74-.07h-.8s.45.04.45.04c.26.02.49.02.74.05l.4.05.87.19c.1.02.21.1.29.15.01.03.02.1.05.09s.06-.01.1-.03ZM10.21,16.21s0,.06-.01.07c-.13-.05-.23-.12-.37-.12l.19.15c.07.05.1.15.18.17s.18,0,.26-.07c-.08-.08-.16-.13-.24-.18ZM12.51,16.19l-.55.19c.22.16.24-.08.55-.19ZM8.04,16.63l1.1-.1c-.46-.1-.9,0-1.35.03-.15.01-.29.02-.44.07h.68ZM11.38,17.01l.73-.12c.06-.01.1-.12.2-.11-.02-.06-.09-.07-.11-.06l-.09.06c-.16,0-.3-.09-.44-.13l-.29-.07c-.08-.02-.15-.03-.21.03h-.31s-.56.15-.56.15c-.08.02-.18-.02-.24.08.16.15.35.17.56.17h.76ZM17.31,16.8s.09.07.14.01c-.06-.11-.15-.16-.22-.18-.14-.04-.2.04-.31.06l-.52.08.91.03ZM2.44,16.97c.18,0,.34.04.5-.03-.25-.05-.52,0-.77,0,.08.09.17.02.27.02Z"/>
      <path  d="M4.92,2.09c-.12.09-.24,0-.34.03-.14.02-.26.07-.41.05-.05,0-.13.04-.18,0-.03-.03-.07-.07-.09-.16l-.38.2c-.09-.02-.17-.03-.25-.07l-.09-.14-.16.31s-.03.09-.08.09c-.13,0-.07-.3-.11-.64v-.07s.02-.02.04,0c.07.08-.01.29.06.48.12-.18.18-.35.24-.57.08.09.09.17.12.3.05-.07.07-.12.15-.14l-.02.12c.13-.01.28.02.4-.02.1-.03.13,0,.22,0l.62.02c.14,0,.28.05.41-.03.11-.07.21-.04.31.03.05.04.15.04.17.12-.1.07-.2.04-.28-.05-.11.04-.25.07-.34.14Z"/>
      <path  d="M14.04,13.87c-.53.1-1.03.22-1.53.38-.1.03-.17.1-.29.09.13-.2.36-.22.56-.28.04-.07.1-.11.18-.15.56-.25,1.14-.38,1.75-.31l-.05.08s-.07-.02-.1-.02-.05.08-.08.09l-.44.12ZM13.79,13.85l.47-.11s0-.02-.02-.03l-.56.1s.05.05.1.04Z"/>
      <path  d="M3.31,13.25c-.11-.05-.18-.09-.31-.09.12-.1.25-.06.37-.01.19.07.38.04.57,0l.88-.15c.37-.06.75-.05,1.07.15-.23.08-.53-.09-.89-.03-.48.08-1.35.28-1.69.12Z"/>
      <path  d="M3.16,7.87l.99-.1c-.13-.07-.28-.01-.42-.02h-.61s-.02-.03-.02-.04l1.09-.08c.32-.02.64-.06.63.13-.54.12-1.08.18-1.63.17-.07,0-.1-.03-.04-.07Z"/>
      <path  d="M4.03,4.84c-.11-.03-.22.02-.32-.07.02-.04.05-.16,0-.19-.08.01-.11.14-.14.21-.07.24-.22.65-.26.65-.02,0-.06-.03-.05-.06l.08-.33c.02-.08.02-.09.06-.17l.19-.35c.04-.07.1-.1.17-.08.08.03.1.12.07.22.1.04.2.01.31.02l.05.17c.45-.14.57.05.43.06l-.34.04c-.06,0-.11.02-.17,0-.03-.01-.05-.09-.08-.15Z"/>
      <path  d="M4.75,12.43c-.33.25-.5.24-.84.15l-.07-.07s-.09,0-.12.04l-.19.23s-.05.03-.08.04c-.03.01-.08-.03-.07-.07l.12-.44c-.22.04-.36.21-.55.36.01-.25.52-.49.6-.46s0,.19.02.34c.08-.07.14-.13.24-.17l.13.12c.06.05.18.08.26.09l.34-.13c.06-.02.11-.09.18-.02Z"/>
      <path  d="M7.17,2.14c-.12.06-.24.02-.36,0-.43-.06-.73.12-.87,0s-.04-.41.12-.43c-.06.08-.19.21-.12.3.12.17.65-.25,1.23.13Z"/>
      <path  d="M6.85,13c-.05.13-.21.17-.31.11.07-.09.19-.15.31-.11Z"/>
      <path  d="M9.21,11.8c-.69-.25-.76-.51-.83-.53-.11-.02-.14-.13-.21-.18l-.2-.14-.34-.33c-.52-.5-.92-1.06-1.18-1.73-.16-.42-.19-.86-.24-1.31-.06-.58,0-1.13.09-1.7.05-.34.23-.7.36-1.02.2-.48.56-1.14.89-1.52.17-.2.33-.41.59-.51-.29.3-.63.52-.79.93.41-.49.87-.9,1.42-1.2-.03-.03-.04-.06-.04-.07s.03,0,.03,0,.05.06.05.05l.45-.25c.14-.08.23-.13.37-.19l.34-.14.22-.07c.37-.12,1.03-.11,1.47-.08.48.03.91.18,1.37.29,1.01.25,2.02.84,2.51,1.77.25.48.5.93.69,1.44.37,1.01.35,2.09.07,3.12-.11.42-.24.79-.49,1.15-.08.12-.14.25-.24.36l-.21.25c-.47.56-1,1.06-1.64,1.44l-.27.16c-.42.25-.72.34-1.22.39-.13.01-.24.01-.38.02l-.36.02c-.36.02-.69-.02-1.04-.05-.28-.03-.53-.1-.8-.2l-.46-.17ZM11.13,1.87l-.55.03c-.26.02-.61.1-.82.23l.55-.14c.16-.04.57-.03.82-.13ZM16.18,7.04c0-2.66-2.16-4.82-4.82-4.82s-4.82,2.16-4.82,4.82,2.16,4.82,4.82,4.82,4.82-2.16,4.82-4.82ZM15.13,3.76c-.13-.29-.37-.45-.58-.64-.3-.26-.62-.46-.99-.61-.07-.03-.13-.1-.24-.07l.75.43.46.36c.22.17.41.34.6.53Z"/>
      <path  d="M15.74,9.88c-.04.14-.19.34-.31.42.05-.12.14-.18.2-.29.03-.05.03-.11.11-.14Z"/>
      <path  d="M9.19,5.42l-.21.12s-.12.02-.16-.02c-.09-.08.02-.25-.07-.27,0,.03-.02.09-.03.1-.05.04-.12-.06-.19-.05-.04,0-.06.04-.08.08l-.37.84c-.06.14-.12.27-.23.38-.06-.19-.03-.35-.04-.52l-.13.44c-.14-.2-.1-.43-.05-.64-.12-.05-.23-.02-.35-.04.11-.06.23-.04.38-.04.06-.2.13-.39.21-.58l-.07-.11s.11,0,.13-.03c.14-.26.27-.5.45-.73l-.56-.15c.23-.05.43.02.64.06l.22-.21c.16-.15.32-.28.53-.4l-.4-.17c.2-.04.36.02.53.08.14-.08.3-.12.46-.08-.02.06-.07.08-.09.11-.01.02.03.06.03.1l.03.19c-.16-.1-.32.05-.45.21l-.25.3c.32.06.61.1.87.28.04.03.13.08.1.15-.04.12-.22.18-.33.11.05-.08.13-.08.2-.12l-.22-.13c-.2-.12-.45-.14-.69-.18-.17.16-.26.36-.37.56-.04.07-.06.1-.02.16.06.08.1.03.18,0s.14.1.1.19c.18.04.29-.22.44-.03.03.04.07.12.04.16-.01.02-.05.02-.06.03s-.03,0-.04-.03l-.06-.12Z"/>
      <path  d="M11.15,10.56l-.06.96s-.08.06-.1.11c-.06-.2-.05-.41-.07-.64l-.06-.81c-.03-.43-.05-.83-.04-1.26l.03-1.27.05-2.04v-1.18s-.09-1.85-.09-1.85c.11.18.05.37.09.56.06-.16,0-.33.1-.5.09.42-.02.9.08,1.31.05-.08,0-.18.07-.25v1.61c.05,1.38.11,2.74.06,4.12l-.04,1.13ZM11.06,9.91c.04-.4.04-.78.04-1.18v-.88s-.05-.97-.05-.97v-.58s-.03-.02-.04-.02v.6s-.06,1.66-.06,1.66c-.02.61.01,1.2.06,1.81.06-.14.03-.3.04-.45Z"/>
      <path  d="M13.48,6.54c-.39.13-.73.28-1.09.43-.06.03-.15.02-.2-.01s-.08-.11-.06-.2l.07-.35s.05-.06.07-.05c.04,0,.06.05.05.1l-.07.4.39-.14c.26-.13.53-.18.81-.24-.04-.1-.08-.15-.08-.23l.22.21.38-.06s.06.03.06.05-.04.04-.06.04l-.39.03c.05.08.09.14.08.23-.1-.02-.12-.1-.16-.2Z"/>
      <path  d="M10.12,9.57c-.14,0-.19-.11-.29-.14l-1.35-.37-.36-.08-.74-.11c.19-.09.55-.03.78.02l1.06.24c.17.04.32.09.49.14.17.05.33.11.4.29Z"/>
      <path  d="M13.56,4.58c-.05-.16-.08-.33-.02-.37.02-.01.08,0,.08.01l.02.13c.07-.08.11-.18.23-.22-.02.19-.16.3-.31.44Z"/>
      <path  d="M12.57,11.42c-.08.02-.18.1-.26.03l.63-.28s.06-.03.08-.03c.02,0,.04.04.04.06-.13.13-.31.18-.48.21Z"/>
      <path  d="M7.46,5.22s-.07,0-.08,0c-.02,0-.02-.05,0-.06l.38-.05v.04s-.3.08-.3.08Z"/>
      <g>
        <path  d="M19.27,17.88l.21.33s-.02.01-.02.01l-.34-.39-.32-.34c-.13-.14-.25-.26-.38-.41-.39-.49-.73-1-1.06-1.53-.15-.24-.34-.42-.45-.68.46.47.8,1.01,1.17,1.54l.34.47.18.24c.14.19.3.34.41.54v-.14s.26.36.26.36Z"/>
        <path  d="M18.79,15.25l-.85-.96-.57-.76c-.03-.1-.11-.17-.11-.29l.5.62.92,1.19c.05.06.11.09.13.17,0,.01.02.02.03.03.07.08.2.23.26.35-.08-.01-.1-.11-.15-.15-.06-.06-.1-.1-.13-.18,0-.01-.02-.02-.03-.02Z"/>
        <path  d="M16.08,13.68c-.07.06-.19.1-.28.1l.78-.73c.08-.08.12-.21.27-.23-.07.23-.23.39-.39.53l-.38.33Z"/>
        <path  d="M20.3,17.15l-.62-.76c.27.19.49.48.64.73,0,0,0,.01.01.02.06.01.1.07.12.14.13.2.28.37.37.61-.21-.22-.36-.47-.51-.72,0,0,0-.02-.01-.02Z"/>
        <path  d="M16.49,14.39s-.05.05-.06.03l-.03-.07-.12-.17s0-.03.01-.04l.2.25Z"/>
      </g>
      <g>
        <path  d="M18.32,6.16s-.05-.04-.05-.05l.73-.08c-.38-.12-1.1.18-1.12-.03,0-.03.01-.06.06-.06l.56-.04c.28-.02.56-.06.82.05.05.02.08.05.08.09,0,.1-.16.07-.3.17-.02-.03-.06-.07-.09-.07l-.7.03Z"/>
        <path  d="M17.65,10.02c-.1,0-.19-.02-.25-.08.06-.04.13-.05.21-.04.29.02.56,0,.85-.06l.39-.03c.07,0,.11.15.06.15l-.52.06h-.73Z"/>
        <path  d="M15.77,2.02c-.05,0-.13-.03-.19-.05.09-.06.2-.05.31-.07.4-.1.8-.13,1.21-.1.05,0,.09.05.08.09,0,.04-.04.07-.09.06-.44-.02-.87,0-1.32.08Z"/>
        <path  d="M16.82,2.59c-.22,0-.4,0-.62,0,.21-.15.44-.12.66-.13l.58-.02s.05.04.06.06c.01.02.01.08-.03.08h-.64Z"/>
        <path  d="M19.6,13.61c-.32.03-.88.03-.84-.06s.23.03.16-.14c.14,0,.31-.02.46.04.05.02.11.11.18.08.01,0,.05,0,.06.02,0,.01,0,.03-.01.07Z"/>
        <path  d="M17.21,10.75c-.15-.03-.26.01-.39.07.05-.11.18-.2.3-.19.05,0,.08.09.09.12Z"/>
        <path  d="M21.39,16.22c-.21.07-.45.1-.66-.02.21-.08.45-.12.66.02Z"/>
        <path  d="M20.78,4.54v-.61s-.01-.74-.01-.74c.05.02.06.06.06.12v.33s0,.73,0,.73l-.03.15s0,.01,0,.02c.03.02.04.06.02.1-.05.1-.01.2-.02.3l-.04.87c-.03-.17-.02-.32-.02-.48l.03-.77s-.01,0-.01-.01Z"/>
        <path  d="M20.78,2.85c0-.16-.04-.31-.02-.5.07.16.04.31.02.5Z"/>
      </g>
    </g>
  </g>
</svg>
        <!-- Loading Refresh Icon (Hidden by default) -->
        <svg id="research-icon-loading" class="w-7 h-7 shrink-0 animate-spin hidden" viewBox="0 0 32 32" fill="currentColor">
  <defs>
    
  </defs>
  <path  d="M27.3,14.91c-.3-3.9-2.45-7.37-5.77-9.27-.11-.06-.21-.11-.26-.18s.09-.37.18-.33c2.51,1.23,4.59,3.27,5.83,5.83.71,1.46,1.08,2.97,1.19,4.58v1.68c-.14,1.44-.43,2.82-1.01,4.14-1.85,4.15-5.77,7.04-10.23,7.5-7.24.74-13.55-4.88-13.92-12.21-.02-.4-.02-.68,0-1.07.15-3.53,1.66-6.82,4.26-9.15,1.87-1.68,4.08-2.78,6.56-3.11l-.28-1.86,3.92,2.4c.14.07.25.15.22.25l-4.05,2.83.22-1.87c-3.71.61-6.75,3.08-8.24,6.52-1.33,3-1.25,6.46.2,9.4.49,1,1.06,1.89,1.82,2.7.26.28.5.55.8.81,1,.88,2.12,1.59,3.37,2.09,4.28,1.72,9,.54,12.15-2.8l.27-.3c2.03-2.28,3.04-5.44,2.8-8.58ZM27.29,19.22c-.58,2.1-1.75,3.93-3.3,5.43l-1.05.86c-3.33,2.51-7.76,3.02-11.53,1.27s-6.21-5.08-6.77-9.13c-.52-3.81.84-7.55,3.61-10.15,1.68-1.59,3.82-2.58,6.08-2.93.12-.02.21.04.26.1.04.05.02.14.01.23l-.14,1.12,2.83-1.96-2.88-1.75.16.98c.02.14.03.25-.02.4-1.89.2-3.66.85-5.24,1.9-5.52,3.67-7.25,11.06-3.84,16.84,3.9,6.61,12.71,8.12,18.5,2.97,2.41-2.14,3.87-5.07,4.05-8.33.1-1.79-.13-3.63-.88-5.23.73,2.6.86,4.76.14,7.38Z"/>
  <ellipse  cx="15.95" cy="16.07" rx="1.71" ry="1.74"/>
</svg>
        <span>Conduct Research</span>
      </button>
```

### Ai Textarea

```html
<div class="text-field-wrapper space-y-2 relative">
                    <div class="flex justify-between items-center mb-2">
                      <label class="field-label block text-sm font-semibold text-gray-900 dark:text-[#f4f4f6] transition-colors">
                        Physical Description & Features
                      </label>
                      <button type="button" onclick="enhanceWithAI(this)" class="group text-[#d2691e] hover:text-[#9e6136] transition-colors bg-gray-50 dark:bg-[#121214] p-1.5 rounded-lg border border-gray-200 dark:border-[#f4f4f6]/10 shadow-sm" title="Refine with AI">
                        <svg class="w-4 h-4 group-hover:drop-shadow-[0_0_6px_#d2691e] group-hover:scale-110 transition-all duration-300" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 27.69 27.71"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <g id="bow">
    <g>
      <path class="cls-1" d="M17.88,27.12c-2.68.81-5.41.77-8.08.01l-1.51-.43c-.9-.26-1.77-.5-2.7-.61-1.36-.16-2.68.09-3.82.83-.08.05-.19.01-.23-.05-.04-.06-.03-.13.02-.22l.46-.92c.27-.54.38-1.71.28-2.4-.11-.82-.33-1.59-.6-2.36l-.71-2.01c-1.31-3.67-1.32-6.5-.02-10.18.31-.89.62-1.73.87-2.64.26-.96.38-1.93.18-2.92-.12-.6-.39-1.15-.69-1.67-.04-.07-.01-.17.03-.2s.13-.03.2.01c2.15,1.31,4.21.65,6.42-.12l1.29-.45c2.81-.98,5.85-1.08,8.7-.18l2.77.88c1.92.61,3.76.74,5.48-.39.07-.04.15-.01.19.02.04.03.1.11.05.17-.22.32-.37.66-.52,1.02-.39.91-.43,1.88-.25,2.86.37,1.96,1.22,3.77,1.68,5.83s.44,4.11-.08,6.14c-.25.99-.57,1.89-.92,2.84s-.68,1.87-.88,2.87c-.19.91-.15,2.16.25,2.97l.42.87c.03.07.05.16,0,.21-.04.05-.16.07-.24.02-1.04-.72-2.26-1.07-3.52-.94-.75.08-1.45.22-2.17.44l-2.34.7ZM18.21,26.66l1.91-.58c.72-.22,1.43-.37,2.19-.43h1.19c.73.08,1.39.29,2.05.63-.27-.58-.44-1.14-.5-1.75-.03-.3-.11-.6,0-.9.04-.72.18-1.41.42-2.1l1-2.89c1.24-3.58,1.13-6.23-.07-9.8-.34-1-.66-1.96-.92-2.98-.22-.87-.3-1.75-.16-2.64.08-.51.29-.94.45-1.45-.6.29-1.18.46-1.82.53h-1.24c-.91-.09-1.74-.33-2.6-.6l-2.51-.8c-2.48-.79-5.46-.66-7.93.17l-2.98,1.01c-.62.21-1.23.31-1.88.38-.97.1-1.9-.05-2.82-.44.27.63.41,1.24.47,1.89.02.24.08.46-.01.7-.03.76-.19,1.48-.43,2.19l-.81,2.4C.2,12.15.05,14.62.93,17.64l1.03,3.02c.31.9.58,1.76.7,2.71v1.24c-.07.58-.25,1.11-.48,1.68,1.79-.81,3.45-.66,5.3-.18l2.39.68c3,.86,5.34.79,8.34-.13Z"/>
      <path class="cls-1" d="M23.04,12.94c-.24-3.1-1.99-5.86-4.67-7.36-.09-.05-.17-.09-.21-.14s.07-.3.14-.26c2.03.98,3.71,2.6,4.72,4.63.57,1.16.87,2.36.96,3.64v1.33c-.11,1.14-.34,2.24-.82,3.29-1.5,3.3-4.67,5.6-8.28,5.96-5.86.59-10.97-3.87-11.27-9.7-.02-.32-.02-.54,0-.85.12-2.81,1.34-5.42,3.45-7.28,1.51-1.33,3.31-2.21,5.31-2.47l-.23-1.48,3.17,1.9c.11.06.2.12.18.2l-3.28,2.25.18-1.48c-3.01.49-5.47,2.45-6.67,5.18-1.07,2.38-1.01,5.14.16,7.47.4.8.86,1.5,1.47,2.14.21.22.4.44.64.64.81.7,1.72,1.26,2.73,1.66,3.46,1.37,7.29.43,9.84-2.22l.22-.24c1.64-1.81,2.46-4.32,2.26-6.82ZM23.04,16.36c-.47,1.67-1.42,3.13-2.67,4.32l-.85.68c-2.7,2-6.28,2.4-9.34,1.01s-5.03-4.04-5.48-7.25c-.42-3.03.68-6,2.92-8.07,1.36-1.26,3.09-2.05,4.93-2.33.1-.01.17.03.21.08.04.04.02.11.01.18l-.11.89,2.29-1.56-2.33-1.39.13.78c.01.11.02.2-.02.32-1.53.16-2.97.67-4.24,1.51-4.47,2.92-5.87,8.79-3.11,13.38,3.16,5.26,10.29,6.45,14.98,2.36,1.95-1.7,3.13-4.03,3.28-6.62.08-1.42-.11-2.89-.71-4.16.59,2.07.69,3.78.11,5.86Z"/>
      <circle class="cls-1" cx="13.85" cy="13.86" r="1.38"/>
    </g>
  </g></svg>
                      </button>
                    </div>
                    <div class="relative">
                      <textarea id="physical-desc" oninput="toggleAiEnhanceBtn(this)" rows="4" class="editable-text-input py-3 px-4 block w-full bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-[#f4f4f6]/20 rounded-xl text-sm text-gray-900 dark:text-[#f4f4f6] transition-all duration-300 resize-none pb-10" placeholder="e.g., Large expressive blue eyes, friendly smile, metallic finish...">Large expressive blue eyes, friendly smile, metallic finish and slightly rounded proportions.</textarea>
                      
                      <div id="ai-generating-overlay" class="ai-generating-overlay absolute inset-0 bg-white/70 dark:bg-[#111111]/80 backdrop-blur-sm z-30 flex flex-col items-center justify-center rounded-xl transition-all hidden">
                        <svg class="w-8 h-8 text-gray-900 dark:text-[#d2691e] animate-spin" fill="currentColor" stroke="currentColor" stroke-width="0.3" viewBox="0 0 32 32"><defs>
    <style>
      .cls-1 {
        fill: #f4f4f6;
      }
    </style>
  </defs>
  <path class="cls-1" d="M27.3,14.91c-.3-3.9-2.45-7.37-5.77-9.27-.11-.06-.21-.11-.26-.18s.09-.37.18-.33c2.51,1.23,4.59,3.27,5.83,5.83.71,1.46,1.08,2.97,1.19,4.58v1.68c-.14,1.44-.43,2.82-1.01,4.14-1.85,4.15-5.77,7.04-10.23,7.5-7.24.74-13.55-4.88-13.92-12.21-.02-.4-.02-.68,0-1.07.15-3.53,1.66-6.82,4.26-9.15,1.87-1.68,4.08-2.78,6.56-3.11l-.28-1.86,3.92,2.4c.14.07.25.15.22.25l-4.05,2.83.22-1.87c-3.71.61-6.75,3.08-8.24,6.52-1.33,3-1.25,6.46.2,9.4.49,1,1.06,1.89,1.82,2.7.26.28.5.55.8.81,1,.88,2.12,1.59,3.37,2.09,4.28,1.72,9,.54,12.15-2.8l.27-.3c2.03-2.28,3.04-5.44,2.8-8.58ZM27.29,19.22c-.58,2.1-1.75,3.93-3.3,5.43l-1.05.86c-3.33,2.51-7.76,3.02-11.53,1.27s-6.21-5.08-6.77-9.13c-.52-3.81.84-7.55,3.61-10.15,1.68-1.59,3.82-2.58,6.08-2.93.12-.02.21.04.26.1.04.05.02.14.01.23l-.14,1.12,2.83-1.96-2.88-1.75.16.98c.02.14.03.25-.02.4-1.89.2-3.66.85-5.24,1.9-5.52,3.67-7.25,11.06-3.84,16.84,3.9,6.61,12.71,8.12,18.5,2.97,2.41-2.14,3.87-5.07,4.05-8.33.1-1.79-.13-3.63-.88-5.23.73,2.6.86,4.76.14,7.38Z"/>
  <ellipse class="cls-1" cx="15.95" cy="16.07" rx="1.71" ry="1.74"/></svg>
                        <span class="mt-2 text-[10px] uppercase tracking-wider font-bold text-gray-900 dark:text-[#d2691e]">Refining...</span>
                      </div>
                    </div>
                  </div>
```

### Add Mascot Card

```html
<div class="bg-gray-50 dark:bg-[#121214] border-2 border-dashed border-gray-300 dark:border-[#f4f4f6]/20 rounded-2xl flex flex-col items-center justify-center cursor-pointer hover:border-[#d2691e] dark:hover:border-[#d2691e] hover:bg-[#d2691e]/10 dark:hover:bg-[#d2691e]/5 transition-all min-h-[220px] group shadow-sm" onclick="showNewDetailView()">
              <div class="w-12 h-12 rounded-full bg-white dark:bg-[#1c1c1c] shadow-sm border border-gray-200 dark:border-[#f4f4f6]/20 flex items-center justify-center mb-3 group-hover:scale-110 transition-all">
                <svg class="w-6 h-6 text-gray-400 group-hover:text-[#d2691e] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              </div>
              <span class="text-sm font-bold text-gray-900 dark:text-[#f4f4f6] group-hover:text-[#d2691e] transition-colors">Add Mascot</span>
              <span class="text-[11px] font-semibold text-gray-500 dark:text-[#A8A29E] mt-1">or Brand Character</span>
            </div>
```

