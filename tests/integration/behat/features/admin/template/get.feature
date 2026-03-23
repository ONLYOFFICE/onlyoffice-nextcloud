Feature: Get templates

  Background:
    Given I am logged in as admin
    Given there are no global templates

  Scenario: No templates exist
    When I retrieve the template list
    Then the template list should be empty

  Scenario: Templates exist
    Given the following global templates exist:
      | name          |
      | report.docx   |
      | budget.xlsx   |
      | slides.pptx   |
    When I retrieve the template list
    Then the response should contain 3 templates
    And each template should have the required metadata

  Scenario Outline: Template type is resolved from file extension
    Given a global template "<name>" exists
    When I retrieve the template list
    Then the response should contain a template with name "<name>" and type "<type>"

    Examples:
      | name        | type         |
      | doc.docx    | document     |
      | sheet.xlsx  | spreadsheet  |
      | deck.pptx   | presentation |
