# Contributing to Laravel Skeletons

Thank you for considering contributing to Laravel Skeletons! Your help is valuable in making this package even better for the Laravel community.

## Code of Conduct

Please note that this project adheres to the [Contributor Covenant Code of Conduct](https://www.contributor-covenant.org/version/2/0/code_of_conduct/). By participating, you are expected to uphold this code. Please report unacceptable behavior to [laci007@gmail.com].

## How You Can Contribute

There are several ways you can contribute to Laravel Skeletons:

* **Reporting Bugs:** If you encounter a bug or unexpected behavior, please create a new issue on [GitHub Issues](https://github.com/Amice/laravel-skeletons.git/issues). Be sure to include:
    * A clear and descriptive title.
    * Steps to reproduce the bug.
    * Your Laravel version, PHP version, and the version of Laravel Skeletons you are using.
    * Any relevant error messages or screenshots.
* **Suggesting Features:** If you have an idea for a new feature or enhancement, please open a new issue on [GitHub Issues](https://github.com/Amice/laravel-skeletons.git/issues) with a clear description of your suggestion and the problem it aims to solve.
* **Submitting Pull Requests:** If you've fixed a bug or implemented a new feature, feel free to submit a pull request (PR) to the `main` branch of the repository. Please ensure your PR follows these guidelines:
    * Clearly explain the purpose of your PR in the description.
    * Reference any related issues.
    * Include tests that cover your changes.
    * Ensure your code follows the project's coding standards (PSR-12).
    * Keep your PR focused on a single issue or feature.
* **Improving Documentation:** If you find areas in the `README.md` or any other documentation that are unclear, incomplete, or incorrect, please submit a pull request with improvements.
* **Spreading the Word:** If you find Laravel Skeletons useful, consider sharing it with others in the Laravel community through blog posts, social media, or discussions.

## Setting Up for Development

If you want to contribute code, here's a basic guide to setting up your development environment:

1.  **Fork the Repository:** Go to [https://github.com/Amice/laravel-skeletons.git](https://github.com/Amice/laravel-skeletons.git) and click the "Fork" button.
2.  **Clone Your Fork:**
    ```bash
    git clone [https://github.com/your-github-username/your-repo.git](https://github.com/your-github-username/your-repo.git)
    cd your-repo
    ```
3.  **Install Dependencies:**
    ```bash
    composer install
    ```
4.  **Create a Branch:**
    ```bash
    git checkout -b feature/your-feature-name
    ```
5.  **Make Your Changes:** Implement your bug fix or new feature.
6.  **Write Tests:** Ensure your changes are covered by tests.
7.  **Format Your Code:** Follow the PSR-12 coding standards. You can use tools like PHP CS Fixer.
8.  **Commit Your Changes:**
    ```bash
    git add .
    git commit -m "feat(your-feature): Add a new cool feature"
    ```
    (Use meaningful commit messages following the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) specification if possible)
9.  **Push to Your Fork:**
    ```bash
    git push origin feature/your-feature-name
    ```
10. **Submit a Pull Request:** Go to the original repository on GitHub and click the "New Pull Request" button. Compare your branch with the `main` branch and submit your PR.

## Running Tests

Please ensure that all tests pass before submitting a pull request. You can typically run the tests using:

```bash
./vendor/bin/phpunit
```

## Coding Standards
Please follow the PSR-12 coding standards.

## License
Laravel Skeletons is open-sourced software licensed under the MIT license. Please review the license file for more information.

Thank you again for your interest in contributing! We appreciate your time and effort.
