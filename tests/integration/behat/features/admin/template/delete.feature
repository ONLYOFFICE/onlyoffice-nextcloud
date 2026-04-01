Feature: Delete templates

  Background:
    Given I am logged in as admin
    Given there are no global templates

  Scenario: Admin deletes an existing template successfully
    When I upload the template "report.docx" as a file
    And I delete the last uploaded template
    Then the template should be deleted successfully
    And the last uploaded template should no longer exist

  Scenario: Deleting a non-existent template returns an error
    When I delete a non-existent template
    Then the deletion should be rejected as not found
