# Contributing

## Commit Messages

This project uses [Conventional Commits](https://www.conventionalcommits.org/).
Every commit subject follows this format:

```text
type(scope): subject line
```

### Allowed types

| Type       | Use for                                                      |
| ---------- | ------------------------------------------------------------ |
| `feat`     | A new feature, including user-facing UI changes              |
| `fix`      | A bug fix, including UI defect fixes                         |
| `docs`     | Documentation only                                           |
| `refactor` | Code change that neither fixes a bug nor adds a feature      |
| `test`     | Adding or correcting tests                                   |
| `chore`    | Build process, tooling, or auxiliary changes                 |
| `ci`       | CI configuration and scripts                                 |
| `security` | Security hardening or vulnerability fixes                    |
| `cleanup`  | Removing dead code or unused assets                          |

Do not invent new types such as `ui:`. UI-only changes use `feat` or `fix`
with an optional scope, for example `feat(layout): add version footer`.
