# M3 cleanup — the `WithCrudTable` trait (a refactor) — Explained for Beginners

> **What this cleanup is, in one sentence:**
> We removed the copy-pasted plumbing shared by the five master-data screens by moving it
> into one reusable **trait** — changing the *structure* of the code without changing what
> it does.

This is a **refactor**: same behaviour, same tests, better organised. It's also a lesson
in three professional habits — the *Rule of Three*, *composition over inheritance*, and
*tests as a safety net*.

Read section **A (concepts)** first if any term is new.

---

## A. Concepts you need first (mini-glossary)

| Term | Plain-English meaning |
|------|----------------------|
| **Refactor** | Improving the *shape* of code without changing what it *does*. No new features, no bug fixes — just tidier structure. |
| **Trait** | A bundle of properties + methods you can **mix into** many classes with a `use` statement. A way to share code between classes that don't (and shouldn't) inherit from a common parent. |
| **DRY** | "Don't Repeat Yourself." Duplicated logic is a maintenance trap — a fix has to be made in every copy. |
| **Rule of Three** | Don't abstract on the 1st or 2nd copy (you don't yet know the real pattern). By the **3rd**, extract it. |
| **Composition over inheritance** | Prefer *assembling* behaviour from small reusable pieces (traits) over building deep parent-class hierarchies. |

---

## B. What's a "trait"? (the mental model)

A trait is a reusable "mix-in" of behaviour:

```php
trait WithCrudTable { public int $perPage = 10; /* … */ }

class Districts extends Component {
    use WithCrudTable;   // Districts now HAS $perPage, notify(), etc. — for free
}
```

The class doesn't *inherit* from anything special; it just **absorbs** the trait's members
as if they were written inside it.

---

## C. The problem it solves

We built five CRUD screens — `Districts`, `Banks`, `Designations`, `Locations`, `Ddos`.
Opening any two side-by-side, ~60–70% was **identical copy-paste**: the same `$perPage`,
`$showForm`, `updatingPerPage()`, `WithPagination`, and the same verbose
`$this->dispatch('notify', …)` calls.

Duplication like that means: to change one shared behaviour (say, a per-page bug), you'd
have to edit **five files** and hope you didn't miss one.

> **The Rule of Three:** we had **five** copies — well past the threshold — and the
> repetition was *measured, not guessed*. That's the signal to refactor.

---

## D. What moved, and what stayed

The discipline of a good refactor is abstracting **only** what's truly identical.

| Moved into `WithCrudTable` (identical everywhere) | Stayed in each screen (genuinely differs) |
|---|---|
| `use WithPagination;` | the query + **filter strategy** (search / district dropdown / cascade) |
| `public int $perPage` + `updatingPerPage()` | `rules()`, `messages()`, the form fields |
| `public bool $showForm` | `save()` specifics (updateOrCreate vs create/update split) |
| a `notify($message, $type)` helper | `delete()` + its guards, `export()`, `render()` |

**Before** — every screen repeated:
```php
use WithPagination;
public int $perPage = 10;
public bool $showForm = false;
public function updatingPerPage(): void { $this->resetPage(); }
// …
$this->dispatch('notify', type: 'success', message: 'District saved.');
```

**After** — every screen:
```php
use WithCrudTable;          // the four lines above are gone
// …
$this->notify('District saved.');   // shorter, and defined once
```

---

## E. Why a trait, not a base class

We deliberately did **not** make an abstract `MasterCrud` parent class. Why?

The five screens share the *mechanics* but differ in the parts that matter — three
different filtering strategies, hand-typed vs auto-generated codes, different delete
guards. A base class would have to expose an "override hook" for each difference; past a
point, that's *harder* to follow than the duplication it replaced.

> **Composition over inheritance:** a trait takes only the truly-common bits and leaves the
> differences **explicit and visible** in each screen. You get the DRY win without coupling
> all five screens to one fragile parent. That's the stronger design here.

**Not included:** the Settings screens (`InterestRates`, `RetirementYear`,
`ContributionShare`). `InterestRates` has no pagination (7 rows), and the singletons aren't
tables at all — forcing them into the trait would be a bad fit. The trait is for
*paginated master CRUD tables*; those screens are a different family.

---

## F. The safety net (the most important part)

A refactor is **behaviour-preserving by definition** — so the only way to *know* we didn't
break anything is to run the tests before and after:

```text
before refactor:  89 passed / 246 assertions
after  refactor:  89 passed / 246 assertions   ← identical → structure changed, behaviour didn't
```

That green-before / green-after is the whole point of having built the tests earlier. A
strong test suite is what turns refactoring from *scary* into *routine*.

---

## G. Files touched

- **New:** `app/Livewire/Concerns/WithCrudTable.php` (the trait).
- **Changed:** the five `app/Livewire/MasterData/*` components now `use` it.
- **Unchanged:** no view, route, or test changes were needed — nothing the user sees
  changed.

---

## ✅ Cleanup done

The trait is ready for the next CRUD screen to reuse from day one, and the five master
screens are meaningfully smaller with their genuine differences now easy to see.
