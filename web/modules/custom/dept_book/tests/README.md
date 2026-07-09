# Book parent protection unit tests

This guide explains the unit tests for this module. It is intended for developers
who are new to PHPUnit, Drupal testing, or this project.

## The problem being tested

Drupal stores book pages in a tree. A page can be a book's top-level page, a
child page, or both a child and a parent of pages below it.

Deleting a parent causes Drupal core to turn its children into separate books.
Archiving a parent leaves its published children without their normal route
through the book navigation. The `BookParentPageProtection` service therefore
protects every book page which has children. Users with the explicit
`override book parent protection` permission are exempt.

## What a unit test proves

A unit test checks one small class without installing Drupal or using the
database. These tests prove that `BookParentPageProtection` makes the expected
decision for the information it receives.

They do not prove that Drupal's forms, hooks, or moderation routes call the
service. That would require kernel or browser tests, which are presently
out of scope.

The moderation-sidebar form has its own validation callback in
`dept_book.module`. When an editor presses its Archive button, that callback
uses this policy before the form's submit handler runs. A protected page stays
unchanged and Drupal displays a normal form error. The storage-level check is
retained as a fallback for archive attempts made outside a form.

## The production class

The class under test is:

`web/modules/custom/dept_book/src/BookParentPageProtection.php`

It has two public methods:

- `isProtected()` asks whether the user lacks the override permission and the
  node's book-outline record says it has children.
- `isArchiveBlocked()` applies that protection only to a new transition into
  the `archived` state. It does not prevent an unrelated save when a page was
  already archived.

The Drupal Book module supplies the outline record through `BookManagerInterface`.
The important `has_children` value is `1` when the page is a parent and `0`
when it is not.

## How the test works

The test file is:

`web/modules/custom/dept_book/tests/src/Unit/BookParentPageProtectionTest.php`

Each test follows the same basic sequence:

1. **Arrange:** create stand-in objects and describe what they return.
2. **Act:** call a method on `BookParentPageProtection`.
3. **Assert:** compare the actual answer with the expected answer.

The stand-in objects are called **mocks**. For example, the real book manager
would query Drupal's storage. The mock returns a small array chosen by the
test. This keeps the test fast and ensures it exercises only the protection
policy.

`expects($this->once())` also verifies that a method is called exactly once.
`with(42, FALSE)` verifies the arguments passed to it. `willReturn(...)`
defines the value the mock gives back. `assertSame()` is the final check that
the policy returned the exact expected boolean value.

## Test scenarios

### `testIsProtected()`

This test uses `protectionProvider()`. A **data provider** runs the same test
several times with different inputs:

- A top-level book page with children is protected.
- A nested page with children is also protected. Protection is not limited to
  the top-level page.
- A book page without children is not protected.
- A node with no book-outline record is not protected.

Each provider row contains the simulated book record, whether the account has
the override, and the expected result.

### `testOverridePermissionAllowsParentChange()`

This test gives the account the override permission. The result must be
unprotected. It also expects the book manager never to be called because the
permission is enough to answer the question.

### `testNewNodeIsNotProtected()`

A new, unsaved node cannot yet have child pages in a stored book outline. This
test confirms that it is not protected and that neither the permission nor the
book manager needs to be checked.

### `testArchiveProtection()`

This test uses `archiveProvider()` to separate archiving from other saves:

- Moving from `published` to `archived` is blocked.
- Saving a page which was already `archived` is not blocked.
- Publishing and submitting for review are not blocked.

Deletion does not need a separate policy method: Drupal's delete access hook
uses `isProtected()` directly.

## Running the test

From the project root, run:

```
ddev exec vendor/bin/phpunit -c phpunit.xml web/modules/custom/dept_book/tests/src/Unit
```

A successful run ends with `OK`. A failure names the test and provider row,
then shows the expected and actual values. Start with that named scenario,
read its provider values, and follow them through the production method.

## Adding another scenario

If new behaviour is another combination of existing inputs, add a clearly
named row to the relevant data provider. Create a separate test method when
the behaviour needs different collaborators or verifies that a method is or
is not called.

Do not change an existing expected value merely to make a failure disappear.
First confirm whether the requirement changed; otherwise the failure may have
found a regression.

## Glossary

- **Node:** Drupal's stored representation of a content item.
- **Book outline:** The tree data connecting book pages to parents and children.
- **Moderation state:** Editorial status such as draft, published, or archived.
- **Permission:** A named capability granted to a Drupal role.
- **Mock:** A test-controlled replacement for a real dependency.
- **Assertion:** A statement of the result the test requires.
- **Data provider:** A method supplying several named input sets to one test.
- **Access result:** Drupal's allowed, forbidden, or neutral answer to an access
  question.
