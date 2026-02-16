<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div id="sad-rules-container">
        <!-- Existing Rules will be loaded here via JS (or pre-rendered PHP) -->
        <?php foreach ( $rules as $index => $rule ): ?>
            <div class="sad-rule-item postbox" data-id="<?php echo esc_attr( $rule['id'] ); ?>">
                <div class="postbox-header">
                    <h2 class="hndle">Rule #<?php echo $index + 1; ?></h2>
                    <div class="handle-actions">
                         <button type="button" class="button-link sad-remove-rule text-danger">Remove</button>
                    </div>
                </div>
                <div class="inside">
                    <p>
                        <label><strong>Trigger Status(es):</strong></label>
                        <input type="text" name="trigger_status" value="<?php echo esc_attr( implode(', ', (array)$rule['trigger_status']) ); ?>" class="regular-text" placeholder="draft, pending (comma separated)">
                    </p>
                    
                    <div class="sad-conditions-box">
                        <h4>Conditions (All must be true)</h4>
                        <div class="sad-conditions-list">
                             <?php if ( ! empty( $rule['conditions'] ) ): ?>
                                <?php foreach ( $rule['conditions'] as $cond ): ?>
                                    <div class="sad-condition-row">
                                        <input type="text" placeholder="Field ID (e.g. invoice_url)" value="<?php echo esc_attr( $cond['field'] ); ?>" class="sad-cond-field">
                                        <select class="sad-cond-operator">
                                            <option value="is" <?php selected( $cond['operator'], 'is' ); ?>>Is</option>
                                            <option value="is_not" <?php selected( $cond['operator'], 'is_not' ); ?>>Is Not</option>
                                            <option value="not_empty" <?php selected( $cond['operator'], 'not_empty' ); ?>>Not Empty</option>
                                            <option value="is_empty" <?php selected( $cond['operator'], 'is_empty' ); ?>>Is Empty</option>
                                        </select>
                                        <input type="text" placeholder="Value" value="<?php echo esc_attr( isset($cond['value']) ? $cond['value'] : '' ); ?>" class="sad-cond-value">
                                        <button type="button" class="button sad-remove-condition">x</button>
                                    </div>
                                <?php endforeach; ?>
                             <?php endif; ?>
                        </div>
                        <button type="button" class="button sad-add-condition">+ Add Condition</button>
                    </div>

                    <hr>

                    <p>
                        <label><strong>Add Tag:</strong></label>
                        <input type="text" name="action_add_tag" value="<?php echo esc_attr( $rule['action_add_tag'] ); ?>" class="regular-text">
                    </p>
                     <p>
                        <label><strong>Remove Tag:</strong></label>
                        <input type="text" name="action_remove_tag" value="<?php echo esc_attr( $rule['action_remove_tag'] ); ?>" class="regular-text">
                    </p>
                    <p>
                        <label><strong>Set Status To:</strong></label>
                        <select name="action_new_status" class="regular-text">
                            <option value="">(No Change)</option>
                            <?php foreach ( $all_statuses as $status_obj ): ?>
                                <option value="<?php echo esc_attr( $status_obj->name ); ?>" <?php selected( isset($rule['action_new_status']) ? $rule['action_new_status'] : '', $status_obj->name ); ?>>
                                    <?php echo esc_html( $status_obj->label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" id="sad-add-rule-btn" class="button button-primary">Add New Rule</button>
    <button type="button" id="sad-save-rules-btn" class="button button-primary" style="float: right;">Save All Rules</button>

    <!-- Template for New Rule -->
    <script type="text/template" id="sad-rule-template">
        <div class="sad-rule-item postbox" data-id="">
                <div class="postbox-header">
                    <h2 class="hndle">New Rule</h2>
                     <div class="handle-actions">
                         <button type="button" class="button-link sad-remove-rule text-danger">Remove</button>
                    </div>
                </div>
                <div class="inside">
                    <p>
                        <label><strong>Trigger Status(es):</strong></label>
                        <input type="text" name="trigger_status" value="" class="regular-text" placeholder="draft, pending (comma separated)">
                    </p>
                    
                    <div class="sad-conditions-box">
                        <h4>Conditions (All must be true)</h4>
                        <div class="sad-conditions-list"></div>
                        <button type="button" class="button sad-add-condition">+ Add Condition</button>
                    </div>

                    <hr>

                    <p>
                        <label><strong>Add Tag:</strong></label>
                        <input type="text" name="action_add_tag" value="" class="regular-text">
                    </p>
                     <p>
                        <label><strong>Remove Tag:</strong></label>
                        <input type="text" name="action_remove_tag" value="<?php echo esc_attr( $rule['action_remove_tag'] ); ?>" class="regular-text">
                    </p>
                    <p>
                        <label><strong>Set Status To:</strong></label>
                        <select name="action_new_status" class="regular-text">
                            <option value="">(No Change)</option>
                            <?php foreach ( $all_statuses as $status_obj ): ?>
                                <option value="<?php echo esc_attr( $status_obj->name ); ?>">
                                    <?php echo esc_html( $status_obj->label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>
            </div>
    </script>

    <!-- Template for Condition Row -->
    <script type="text/template" id="sad-condition-template">
        <div class="sad-condition-row">
            <input type="text" placeholder="Field ID (e.g. invoice_url)" value="" class="sad-cond-field">
            <select class="sad-cond-operator">
                <option value="is">Is</option>
                <option value="is_not">Is Not</option>
                <option value="not_empty">Not Empty</option>
                <option value="is_empty">Is Empty</option>
            </select>
            <input type="text" placeholder="Value" value="" class="sad-cond-value">
            <button type="button" class="button sad-remove-condition">x</button>
        </div>
    </script>
    
    <style>
        .sad-rule-item { margin-bottom: 20px; }
        .sad-condition-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .sad-cond-field { width: 200px; }
        .sad-conditions-box { background: #f9f9f9; padding: 10px; border: 1px solid #ddd; margin: 10px 0; }
    </style>
</div>
