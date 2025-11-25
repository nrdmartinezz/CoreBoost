# Contributing to CoreBoost

Thank you for your interest in contributing to CoreBoost! This document provides guidelines and information for contributors.

## Getting Started

CoreBoost is a WordPress performance optimization plugin focused on improving Core Web Vitals, particularly Largest Contentful Paint (LCP). We welcome contributions from developers of all skill levels.

### Development Environment Setup

To set up your development environment, you'll need a local WordPress installation with the following requirements:

**System Requirements:**
- WordPress 5.0 or higher
- PHP 7.4 or higher  
- MySQL 5.6 or higher

**Recommended Development Tools:**
- Local WordPress environment (Local by Flywheel, XAMPP, or Docker)
- Code editor with PHP support (VS Code, PhpStorm)
- Git for version control
- Browser developer tools for testing

### Repository Structure

The CoreBoost repository follows WordPress plugin standards with the following structure:

```
CoreBoost/
├── coreboost.php          # Main plugin file
├── assets/                # CSS, JS, and image assets
│   ├── admin.css         # Admin interface styles
│   ├── admin.js          # Admin interface scripts
│   └── settings.js       # Settings page functionality
├── languages/            # Translation files
├── README.md            # Project documentation
├── CHANGELOG.md         # Version history
├── CONTRIBUTING.md      # This file
└── LICENSE              # GPL v2 license
```

## How to Contribute

### Reporting Bugs

When reporting bugs, please include the following information to help us reproduce and fix the issue:

**Environment Details:**
- WordPress version
- PHP version
- Active theme and plugins
- Browser and version

**Bug Description:**
- Clear description of the issue
- Steps to reproduce the problem
- Expected vs actual behavior
- Screenshots or error messages if applicable

**Performance Data:**
- PageSpeed Insights URLs (before/after if applicable)
- Debug mode output if relevant
- Browser console errors

### Suggesting Features

We welcome feature suggestions that align with CoreBoost's mission of improving WordPress performance. When suggesting features, please provide:

**Feature Description:**
- Clear explanation of the proposed feature
- Use case and benefits
- How it relates to Core Web Vitals or performance optimization

**Implementation Considerations:**
- Potential impact on existing functionality
- Compatibility concerns
- Performance implications

### Code Contributions

#### Before You Start

Before beginning work on a significant contribution, please open an issue to discuss your proposed changes. This helps ensure your work aligns with the project's direction and prevents duplicate efforts.

#### Coding Standards

CoreBoost follows WordPress coding standards to ensure consistency and maintainability:

**PHP Standards:**
- Follow WordPress PHP Coding Standards
- Use proper DocBlock comments for all functions and classes
- Implement proper sanitization and validation for user inputs
- Use WordPress hooks and filters appropriately

**JavaScript Standards:**
- Follow WordPress JavaScript Coding Standards
- Use modern ES6+ syntax where appropriate
- Ensure compatibility with WordPress's jQuery implementation

**CSS Standards:**
- Follow WordPress CSS Coding Standards
- Use consistent naming conventions
- Ensure responsive design principles

#### Performance Considerations

Since CoreBoost is a performance plugin, all contributions must consider performance impact:

**Code Efficiency:**
- Minimize database queries
- Use WordPress caching mechanisms
- Avoid blocking operations in critical paths
- Implement lazy loading where appropriate

**Testing Requirements:**
- Test on various WordPress configurations
- Verify compatibility with popular themes and plugins
- Measure performance impact using PageSpeed Insights
- Test with debug mode enabled

#### Pull Request Process

When submitting a pull request, please follow these guidelines:

**Branch Naming:**
- Use descriptive branch names (e.g., `feature/lazy-loading-improvement`, `bugfix/css-defer-issue`)
- Create feature branches from the `main` branch

**Commit Messages:**
- Use clear, descriptive commit messages
- Reference issue numbers when applicable
- Follow conventional commit format when possible

**Pull Request Description:**
- Provide a clear description of changes
- Include testing instructions
- Reference related issues
- Include before/after performance data if applicable

**Code Review Process:**
- All pull requests require review before merging
- Address feedback promptly and professionally
- Ensure all tests pass before requesting review

### Testing Guidelines

Comprehensive testing is crucial for a performance optimization plugin:

**Manual Testing:**
- Test on fresh WordPress installations
- Verify functionality with popular themes (Twenty Twenty-Three, Astra, GeneratePress)
- Test with common plugins (Elementor, WooCommerce, Contact Form 7)
- Validate performance improvements using PageSpeed Insights

**Automated Testing:**
- Write unit tests for new functionality
- Ensure existing tests continue to pass
- Test edge cases and error conditions

**Performance Testing:**
- Measure LCP improvements
- Verify CSS and JavaScript optimization
- Test lazy loading exclusions
- Validate critical CSS functionality

## Community Guidelines

### Code of Conduct

We are committed to providing a welcoming and inclusive environment for all contributors. Please treat all community members with respect and professionalism.

**Expected Behavior:**
- Use welcoming and inclusive language
- Respect differing viewpoints and experiences
- Accept constructive criticism gracefully
- Focus on what's best for the community

**Unacceptable Behavior:**
- Harassment or discriminatory language
- Personal attacks or trolling
- Publishing private information without permission
- Other conduct inappropriate for a professional setting

### Communication Channels

**GitHub Issues:** Use for bug reports, feature requests, and technical discussions
**GitHub Discussions:** Use for general questions, ideas, and community discussions
**Pull Request Comments:** Use for code-specific discussions and reviews

## Recognition

Contributors who make significant contributions to CoreBoost will be recognized in the project documentation and changelog. We appreciate all forms of contribution, from code submissions to bug reports and documentation improvements.

## Questions and Support

If you have questions about contributing or need help getting started, please don't hesitate to reach out through GitHub Issues or Discussions. We're here to help and welcome new contributors to the project.

Thank you for helping make CoreBoost better for the WordPress community!
