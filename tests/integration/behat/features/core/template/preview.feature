Feature: Template preview

  Background:
    Given I am logged in as a regular user

  Scenario: Requesting a preview for an existing template is processed without error
    Given a global template "letter.docx" exists
    When I request a preview for the template
    Then the preview request should not be rejected

  Scenario: Requesting a preview for a non-existent template returns not found
    When I request a preview for a non-existent template
    Then the preview should not be found

  Scenario: Requesting a preview with a zero file id returns a bad request error
    When I request a preview with a zero file id
    Then the preview request should be rejected

  Scenario: Requesting a preview with zero dimensions returns a bad request error
    When I request a preview with zero dimensions
    Then the preview request should be rejected
