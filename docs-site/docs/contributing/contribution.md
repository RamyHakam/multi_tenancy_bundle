---
title: "Contributing"
---

Thank you for considering contributing to the Symfony Multi-Tenancy Bundle! 🎉 We welcome all suggestions, bug reports, and pull requests. Below are some friendly guidelines to help you get started.

## 💡 1. Suggesting New Features

We love new ideas! If you have an idea for a feature or improvement:

1. Check the [issue tracker](https://github.com/RamyHakam/multi_tenancy_bundle/issues) to see if it’s already discussed. 🔍
2. Open a new issue with a clear title and detailed description of your proposal:

   * **What problem does it solve?**
   * **How would it work in practice?**
   * **Any example code or usage patterns.**
3. Engage in the discussion: feedback is iterative. 🔁

Alternatively, share your ideas and feedback in our [GitHub Discussions](https://github.com/RamyHakam/multi_tenancy_bundle/discussions) forum. 💬

## 🛠 2. Contributing Code (Features, Improvements & Fixes)

Ready to contribute code whether adding new features, fixing bugs, or improving existing functionality? Follow these steps:

1. **Fork** the repository to your own GitHub account. 🍴
2. **Create a feature branch** named descriptively, e.g., `feature/performance-optimizations` or `bugfix/fix-connection-error`. 🌿
3. **Implement your changes**, adhering to the project’s coding standards and adding tests for any new or modified behavior. 📝
4. **Update documentation** (in `docs/`) to cover your changes. 📚
5. **Submit a Pull Request** against the `main` branch, referencing the related issue(s). Include:

   * A summary of your changes. 🗒️
   * Usage examples or migration steps if applicable. 🚀
   * Any breaking changes or compatibility notes. ⚠️

## 🎨 3. Code Style

Please follow these conventions:

* **PSR-12** coding standard. 🎯
* Declare **strict types** in PHP files:

  ```php
  declare(strict_types=1);
  ```
* Add **docblocks** for all public methods and complex logic. 📄
* Run and fix issues with:

  ```bash
  composer fix
  ```

## ✅ 4. Running Tests

We use PHPUnit to ensure quality. To run the full test suite:

```bash
composer install
vendor/bin/phpunit --testsuite=unit
vendor/bin/phpunit --testsuite=integration
```

* Ensure both **main** and **tenant** logic are covered by tests. 🛡️
* Add new tests for any bug fixes or features. 🧪
* CI will run tests automatically on PRs. ⚙️

---

We appreciate every contribution—big or small. By following these guidelines, you help keep the project maintainable and high-quality. Happy coding! 🚀
