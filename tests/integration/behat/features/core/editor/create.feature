Feature: Editor create documents

  Background:
    Given I am logged in as a regular user

  Scenario Outline: Creating a blank file of each supported type
    When I create a blank file named "<name>" in my home folder
    Then the file should be created successfully
    And the created file should be named "<name>"

    Examples:
      | name               |
      | document.docx      |
      | spreadsheet.xlsx   |
      | presentation.pptx  |

  Scenario: Creating a file when one with the same name already exists deduplicates the name
    Given a file named "report.docx" already exists in my home folder
    When I create a blank file named "report.docx" in my home folder
    Then the file should be created successfully
    And the created file should have a different name than "report.docx"

  Scenario: Creating a file from a global template
    Given a global template "contract.docx" exists
    When I create a file from that template in my home folder
    Then the file should be created successfully

  Scenario: Creating a file with no name fails
    When I create a file with no name in my home folder
    Then the creation should fail
