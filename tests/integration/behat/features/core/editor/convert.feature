Feature: Editor convert

  Background:
    Given I am logged in as a regular user

  Scenario: Converting a legacy document produces an editable DOCX file
    Given I have the "demo.doc" asset file in my home folder
    When I convert the file
    Then the conversion should succeed
    And the converted file should be a "docx" file

  Scenario: Converting a file that is already in an editable format is not required
    Given I have a "docx" file in my home folder
    When I convert the file
    Then the conversion should not be required

  Scenario: Converting a file with an unsupported format is not allowed
    Given I have a "xyz" file in my home folder
    When I convert the file
    Then the conversion should fail with an unsupported format error
