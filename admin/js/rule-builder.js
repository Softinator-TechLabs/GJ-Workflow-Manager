jQuery(document).ready(function ($) {
  // Add Rule
  $("#sad-add-rule-btn").on("click", function () {
    var template = $("#sad-rule-template").html();
    $("#sad-rules-container").append(template);
  });

  // Remove Rule
  $(document).on("click", ".sad-remove-rule", function () {
    if (confirm("Are you sure?")) {
      $(this).closest(".sad-rule-item").remove();
    }
  });

  // Add Condition
  $(document).on("click", ".sad-add-condition", function () {
    var template = $("#sad-condition-template").html();
    $(this).prev(".sad-conditions-list").append(template);
  });

  // Remove Condition
  $(document).on("click", ".sad-remove-condition", function () {
    $(this).closest(".sad-condition-row").remove();
  });

  // Save Rules
  $("#sad-save-rules-btn").on("click", function () {
    var rules = [];

    $(".sad-rule-item").each(function () {
      var $el = $(this);
      var rule = {
        id: $el.data("id") || "rule_" + Math.random().toString(36).substr(2, 9),
        trigger_status: $el
          .find('input[name="trigger_status"]')
          .val()
          .split(",")
          .map((s) => s.trim())
          .filter((s) => s !== ""),
        action_add_tag: $el.find('input[name="action_add_tag"]').val(),
        action_remove_tag: $el.find('input[name="action_remove_tag"]').val(),
        action_new_status: $el.find('select[name="action_new_status"]').val(),
        conditions: [],
      };

      $el.find(".sad-condition-row").each(function () {
        var $row = $(this);
        rule.conditions.push({
          field: $row.find(".sad-cond-field").val(),
          operator: $row.find(".sad-cond-operator").val(),
          value: $row.find(".sad-cond-value").val(),
        });
      });

      rules.push(rule);
    });

    // Send AJAX
    $.post(
      sad_workflow_vars.ajax_url,
      {
        action: "sad_save_rules",
        nonce: sad_workflow_vars.nonce,
        rules: JSON.stringify(rules),
      },
      function (response) {
        if (response.success) {
          alert("Rules Saved Successfully!");
        } else {
          alert("Error: " + response.data);
        }
      },
    );
  });
});
