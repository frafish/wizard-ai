<?php
// Register frontend form filling tool
wp_register_ability(new class implements \WP_AI_Client_Ability_Interface {
    public function get_name(): string { return 'wpab__ai__fill_form_field'; }
    public function get_description(): string { return 'Fill a frontend HTML form field with a specific value. Useful when assisting a user to complete a form.'; }
    
    public function get_parameters(): array {
        return [
            'type' => 'object',
            'properties' => [
                'field_name_or_id' => [
                    'type' => 'string',
                    'description' => 'The name attribute or ID attribute of the form field to fill.'
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'The value to fill into the field.'
                ]
            ],
            'required' => ['field_name_or_id', 'value']
        ];
    }
    
    public function execute(array $args): string {
        $field = $args['field_name_or_id'] ?? '';
        $val = $args['value'] ?? '';
        
        $frontend_action = [
            'type' => 'fill_form',
            'fieldName' => $field,
            'fieldValue' => $val
        ];
        
        // Save the action to a transient tied to this request so it can be picked up by the API response
        // Or better yet, we can just return a specific JSON string that the chat.php interceptor catches
        // Chatbot parses json responses if they have 'frontend_actions'
        return json_encode(['frontend_actions' => [$frontend_action]]);
    }
});
