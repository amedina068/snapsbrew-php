# Contributing

Thank you for your help. This file tells you how to prepare a change.

## Set up the project

1. Install PHP 8.2 or later and Composer.
2. Clone the repository.
3. Install the dependencies:

```bash
composer install
```

## Make a change

1. Create a branch from `main`.
2. Write a test for the change first. Then write the code.
3. Run the tests:

```bash
composer test
```

4. Run the static analysis:

```bash
composer analyse
```

5. Format the code:

```bash
composer format
```

6. Add a line to the `Unreleased` section of `CHANGELOG.md`.
7. Open a pull request. Complete the pull request template.

## Rules

- Each response is a typed, read-only value object. Do not return raw arrays.
- Do not add a dependency if the PHP standard library can do the job.
- Do not add telemetry.
- Never put a token, a signing key or another secret in the code, the tests or the logs.
- The integration tests must stay read-only. A preview call is the only exception.

## Write the documentation

Write documentation, comments and commit messages in Simple English (ASD-STE100):

- Use short sentences. An instruction has a maximum of 20 words. A description has a maximum of 25 words.
- Use the active voice. Use the imperative for instructions.
- Use one word for one meaning.
- Do not use contractions or semicolons.
- Use "can", "will" and "must". Do not use "should", "may", "might", "could" or "would".

## Pull request titles

The title of a pull request must follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/). The `PR title` check of the CI fails if the title does not follow it. The titles of the merged pull requests become the lines of the release notes, so a clear title helps each user.

Start the title with a type: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore` or `revert`. A scope in parentheses is optional. Examples:

- `feat: add a method to cancel a snap batch`
- `fix(laravel): read the instance URL from the config file`
- `docs: add a webhook example to the README`

## Triage

Each new issue gets the `needs-triage` label. The maintainer reads the issue and replaces `needs-triage` with a type label, for example `bug`, `enhancement`, `documentation` or `question`.

## Report a bug

Open an issue with the bug template. To report a security problem, read [SECURITY.md](SECURITY.md). Do not open a public issue for a security problem.
