Feature: Add templates

  Background:
    Given I am logged in as admin
    Given there are no global templates

  Scenario: Admin uploads a valid template and receives its metadata
    When I upload the template "letter.docx" as a file
    Then the template should be uploaded successfully with its metadata

  Scenario: Uploading a file with an unsupported extension is rejected
    When I upload the template "image.png" as a file
    Then the upload should be rejected as unsupported

  Scenario: Uploading a template with a duplicate name is rejected
    And a global template "memo.docx" exists
    When I upload the template "memo.docx" as a file
    Then the upload should be rejected as a duplicate
