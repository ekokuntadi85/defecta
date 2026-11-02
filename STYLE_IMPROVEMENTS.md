# Style Design Improvements — Next Version

**Created:** 2026-11-02  
**Status:** Planning Phase  
**Target:** Make design polished, accessible, modern CSS.

---

## Overview

Current design is functional but needs refinement in:
- Mobile UX (safe areas, touch targets, density)
- Accessibility (contrast ratios, focus indicators)
- CSS maintainability (duplicate media queries, standardized scales)
- Modern CSS adoption (fluid typography, logical properties, cascade layers)

---

## Phase 1: CSS Cleanup & Variables (High Impact)

### 1.1 Remove Duplicate Media Queries
**File:** `public/assets/css/style.css`  
**Issue:** `@media (max-width: 768px)` appears at lines 1183 and 1828  
**Action:** Merge all 768px rules into single consolidated block at end of file

### 1.2 Standardize Border Radius Scale
**File:** `public/assets/css/style.css`  
**Current State:**
- Cards: 10px
- Modals: 14px
- Inputs: 8px
- Buttons: 8px/10px/12px (inconsistent)

**Action:** Define in `:root`:
```css
--radius-sm: 6px;
--radius-md: 10px;
--radius-lg: 14px;
```
Replace all hardcoded border-radius values with tokens

### 1.3 Elevation Tokens
**File:** `public/assets/css/style.css`  
**Action:** Add shadow depth tokens:
```css
--elev-1: 0 1px 3px rgba(0, 0, 0, .3);
--elev-2: 0 4px 12px rgba(0, 0, 0, .4);
--elev-3: 0 8px 32px rgba(0, 0, 0, .5);
```
Replace inline `box-shadow` values with tokens

---

## Phase 2: Mobile UX (High Impact)

### 2.1 Safe Area Insets (Notched Devices)
**File:** `public/assets/css/style.css`  
**Changes:**
- `.bottom-nav`: Add `padding-bottom: env(safe-area-inset-bottom)`
- `.bulk-bar.show` (mobile): Change `bottom` to `calc(95px + env(safe-area-inset-bottom))`
- `.fab`: Add `margin-bottom: env(safe-area-inset-bottom)`

**Reason:** iPhone notches, Dynamic Island, Android cutouts need space

### 2.2 Touch Targets (44px minimum)
**File:** `public/assets/css/style.css`  
**Items to audit & fix:**
- `.header-nav-link`: Set `min-height: 44px`, adjust padding
- `.filter-mode-btn`: Set `min-height: 44px`
- `.page-btn`: Verify ≥44px
- All `.btn-*` variants on mobile

**Reason:** WCAG 2.1 Level AAA, Apple HIG guideline

### 2.3 FAB Size Reduction
**File:** `public/assets/css/style.css`  
**Change:** `.fab` width/height: 60px → 56px  
**Reason:** Material Design standard, better thumb reachability on small screens

### 2.4 Mobile Card Density (<375px)
**File:** `public/assets/css/style.css`  
**Action:** Add new breakpoint:
```css
@media (max-width: 375px) {
  .mobile-card {
    padding: 12px; /* from 16px */
  }
  .mobile-card-list {
    gap: 8px; /* from 12px */
  }
}
```

**Reason:** Optimize for tight mobile screens without sacrificing readability

---

## Phase 3: Accessibility (High Impact)

### 3.1 Fix Contrast Ratios
**File:** `public/assets/css/style.css`  
**Issue:** `--text-muted` (#7a94ac) on `--surface` (#18212f) = 3.8:1 (below WCAG AA 4.5:1)  
**Action:** Change `--text-muted: #7a94ac` → `#9db2c6` (improved to ~4.8:1)

**Reason:** WCAG 2.1 Level AA compliance for body text

### 3.2 Focus Visible States
**File:** `public/assets/css/style.css`  
**Action:** Add distinct `:focus-visible` styles:
```css
.btn:focus-visible,
.form-control:focus-visible,
.page-btn:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
```

**Reason:** Keyboard users need clear focus indicators separate from hover

### 3.3 Enhanced Motion Preferences
**File:** `public/assets/css/style.css`  
**Action:** Expand `@media (prefers-reduced-motion: reduce)`:
- Disable `.fab` rotate animation
- Disable `.bulk-bar` slide-in animation
- Keep all other transitions minimal

**Reason:** Better UX for users with vestibular disorders

### 3.4 High Contrast Mode Support
**File:** `public/assets/css/style.css`  
**Action:** Add:
```css
@media (prefers-contrast: more) {
  .btn-primary {
    border: 2px solid var(--accent);
  }
  input:focus {
    outline: 3px solid var(--accent);
  }
}
```

**Reason:** Support users with low vision

### 3.5 ARIA Labels on Components
**Files:** 
- `public/includes/modal_backup.php`: Add `role="dialog"`, `aria-modal="true"`, `aria-labelledby`
- `public/includes/modal_add.php`: Already done; verify complete
- `public/assets/js/app.js`: Add `aria-expanded` on autocomplete dropdown toggle

**Reason:** Screen reader accessibility

---

## Phase 4: Modern CSS Adoption (Medium Impact)

### 4.1 Fluid Typography
**File:** `public/assets/css/style.css`  
**Changes:**
- `.stat-card .stat-val`: `font-size: 28px` → `clamp(20px, 4vw, 28px)`
- `.summary-val`: `font-size: 32px` → `clamp(24px, 5vw, 32px)`
- `.header-info h1`: `font-size: 17px` → `clamp(16px, 3vw, 20px)`

**Reason:** Responsive scaling without additional media queries

### 4.2 Logical Properties (RTL Ready)
**File:** `public/assets/css/style.css`  
**Changes:**
- Replace `margin-left`/`margin-right` with `margin-inline`
- Replace `padding-left`/`padding-right` with `padding-inline`
- Replace `border-left`/`border-right` with `border-inline`

**Reason:** Prepare for RTL language support (Indonesian future-proofing)

### 4.3 Cascade Layers
**File:** `public/assets/css/style.css`  
**Action:** Wrap CSS in layers:
```css
@layer base {
  /* CSS variables, reset, HTML elements */
}
@layer components {
  /* Buttons, cards, tables, forms */
}
@layer utilities {
  /* Toast, spinner, animations, helpers */
}
```

**Reason:** Clearer specificity management, easier overrides

---

## Phase 5: Navigation & Layout (Medium Impact)

### 5.1 Bottom Nav Labels on Mobile
**Files:** `public/index.php`, `public/laporan.php`, `public/staff.php`  
**Action:** Show nav text labels only on active item on mobile (≤768px)  
**Implementation:**
- Add CSS for `.nav-item span: display: none` on mobile
- Show `.nav-item.active span: display: inline`

**Reason:** Save space, reduce visual clutter

### 5.2 Bottom Nav Item Accessibility
**File:** `public/includes/header.php` (or nav include)  
**Action:** Add `aria-label="Defecta"`, `aria-label="Laporan"`, etc. to `.nav-item` elements

**Reason:** Screen reader clarity

### 5.3 Container Queries (Optional, Phase 5.5)
**File:** `public/assets/css/style.css`  
**Scope:** Apply to reusable components:
- `.stat-card` responsive to container width
- `.toolbar` responsive to container width

**Reason:** Component-level responsive design without viewport dependency

---

## Phase 6: Verification & Testing (Gate)

### 6.1 Unit & Integration Tests
**Command:** `./vendor/bin/phpunit --configuration phpunit.xml`  
**Expected:** 75/75 passed, 1 skipped  
**Why:** Ensure no regression in backend logic

### 6.2 Visual Regression
**Devices:**
- Desktop (1024px, Chrome)
- Tablet (768px, Chrome dev tools)
- Mobile (375px, Chrome dev tools)

**Flows to test:**
- Login & PIN entry
- Add defecta via modal
- Bulk select rows
- Report generation & print
- Backup/restore modal

### 6.3 Accessibility Audit
**Tools:**
- Chrome Lighthouse (Accessibility score)
- axe DevTools browser extension
- Manual keyboard navigation (Tab, Enter, Escape)
- Screen reader test (NVDA/JAWS on Windows, VoiceOver on Mac)

**Checklist:**
- [ ] Contrast ratios all ≥4.5:1
- [ ] All interactive elements keyboard accessible
- [ ] Focus indicators visible on all elements
- [ ] Form labels associated with inputs
- [ ] Modal dialogs have proper ARIA roles
- [ ] Color not sole indicator of meaning

### 6.4 Performance Check
**Metrics:**
- CSS file size (gzipped)
- Paint timing (Lighthouse)
- Layout shifts (CLS)

---

## Files Affected

| File | Phase | Change Type |
|------|-------|-------------|
| `public/assets/css/style.css` | 1,2,3,4 | Major refactor |
| `public/assets/js/app.js` | 3.5 | Minor (ARIA attrs) |
| `public/includes/modal_add.php` | 3.5 | Minor (verify ARIA) |
| `public/includes/modal_backup.php` | 3.5 | Minor (add ARIA) |
| `public/includes/header.php` | 5 | Minor (nav labels) |
| `public/index.php`, `laporan.php`, `staff.php` | 5 | Minor (CSS only) |

---

## Execution Order (Recommended)

1. **Phase 1** (CSS Cleanup) — Low risk, high maintainability
2. **Phase 3** (Accessibility) — Compliance critical
3. **Phase 2** (Mobile UX) — User-facing polish
4. **Phase 4** (Modern CSS) — Nice-to-have modernization
5. **Phase 5** (Navigation) — Polish & UX refinement
6. **Phase 6** (Verification) — Gate before merge to main

---

## Branch Strategy

**Current branch:** `feature/user-identity-and-audit`  
**Next branch:** `feature/style-improvements-v2`  

**Process:**
1. Create branch from `main`
2. Execute phases sequentially
3. Run Phase 6 verification after each phase
4. Commit incrementally (one commit per phase)
5. Create PR with this plan as description
6. Merge after code review & tests pass

---

## Success Criteria

- [ ] All WCAG 2.1 Level AA requirements met
- [ ] Mobile UX improved (safe areas, touch targets)
- [ ] CSS maintainability improved (no duplicates, standardized tokens)
- [ ] All tests passing (75/75 PHPUnit, Lighthouse ≥90)
- [ ] No visual regression on desktop/tablet/mobile
- [ ] Keyboard navigation works end-to-end
- [ ] Screen reader tested and verified

---

**Next:** Await confirmation to create branch and execute Phase 1.
