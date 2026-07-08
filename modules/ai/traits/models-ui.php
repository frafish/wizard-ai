<?php
namespace WizardAi\Modules\Ai\Traits;

trait ModelsUi {
    public function wb_ai_models_page_html() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $enabled_models = get_option('wbai_enabled_models', []);
        $cron_enabled = wp_next_scheduled('wbai_update_models_cron') !== false;

        $models_data = [];
        $providers = [];
        $families = [];
        $all_capabilities = [];

        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements([], []);
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                $providerId = $providerMetadata->getProvider()->getId();
                if (!in_array($providerName, $providers)) {
                    $providers[] = $providerName;
                }
                
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $name = $modelMeta->getName() ?: $id;
                    $uid = $providerId . '|' . $id;
                    
                    $family = 'Other';
                    if (stripos($name, 'claude') !== false) {
                        $family = 'Claude';
                    } elseif (stripos($name, 'gpt') !== false || stripos($name, 'o1') !== false || stripos($name, 'openai') !== false) {
                        $family = 'GPT';
                    } elseif (stripos($name, 'gemini') !== false || stripos($name, 'google') !== false) {
                        $family = 'Gemini / Google';
                    } elseif (stripos($name, 'gemma') !== false) {
                        $family = 'Gemma';
                    } elseif (stripos($name, 'llama') !== false) {
                        $family = 'Llama';
                    } elseif (stripos($name, 'mistral') !== false || stripos($name, 'mixtral') !== false || stripos($name, 'codestral') !== false || stripos($name, 'voxtral') !== false || stripos($name, 'devstral') !== false || stripos($name, 'pixtral') !== false || stripos($name, 'mathstral') !== false || stripos($name, 'magistral') !== false || stripos($name, 'ministral') !== false) {
                        $family = 'Mistral';
                    } elseif (stripos($name, 'qwen') !== false) {
                        $family = 'Qwen';
                    } elseif (stripos($name, 'glm') !== false) {
                        $family = 'GLM';
                    } elseif (stripos($name, 'deepseek') !== false) {
                        $family = 'DeepSeek';
                    } elseif (stripos($name, 'nano banana') !== false || stripos($name, 'banana') !== false) {
                        $family = 'Nano Banana';
                    } elseif (stripos($name, 'phi') !== false) {
                        $family = 'Phi';
                    } elseif (stripos($name, 'perplexity') !== false || stripos($name, 'sonar') !== false) {
                        $family = 'Perplexity';
                    } elseif (stripos($name, 'cohere') !== false || stripos($name, 'command') !== false) {
                        $family = 'Cohere';
                    } elseif (stripos($name, 'veo') !== false) {
                        $family = 'Veo';
                    } elseif (stripos($name, 'nova') !== false) {
                        $family = 'Amazon Nova';
                    } elseif (stripos($name, 'nemotron') !== false) {
                        $family = 'Nemotron';
                    } elseif (stripos($name, 'lyria') !== false) {
                        $family = 'Lyria';
                    }
                    if (!in_array($family, $families)) {
                        $families[] = $family;
                    }
                    
                    $capabilities = [];
                    foreach ($modelMeta->getSupportedCapabilities() as $cap) {
                        $capabilities[] = $cap->value;
                        if (!in_array($cap->value, $all_capabilities)) {
                            $all_capabilities[] = $cap->value;
                        }
                    }
                    
                    if ($family === 'Veo' && !in_array('video_generation', $capabilities)) {
                        $capabilities[] = 'video_generation';
                        if (!in_array('video_generation', $all_capabilities)) {
                            $all_capabilities[] = 'video_generation';
                        }
                    }
                    if ($family === 'Lyria' && !in_array('music_generation', $capabilities)) {
                        $capabilities[] = 'music_generation';
                        if (!in_array('music_generation', $all_capabilities)) {
                            $all_capabilities[] = 'music_generation';
                        }
                    }

                    $models_data[] = [
                        'uid' => $uid,
                        'id' => $id,
                        'name' => $name,
                        'provider' => $providerName,
                        'family' => $family,
                        'capabilities' => $capabilities,
                        'is_enabled' => empty($enabled_models) || in_array($uid, $enabled_models)
                    ];
                }
            }
        }
        
        sort($providers);
        sort($families);
        sort($all_capabilities);
        
        $capability_labels = [
            'text_generation' => 'Text Generation',
            'image_generation' => 'Image Generation',
            'text_to_speech_conversion' => 'Text to Speech',
            'speech_generation' => 'Speech Generation',
            'music_generation' => 'Music Generation',
            'video_generation' => 'Video Generation',
            'embedding_generation' => 'Embeddings',
            'chat_history' => 'Chat History'
        ];
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('AI Models', 'wizard-ai'); ?></h1>
            <p><?php esc_html_e('Manage available AI models, filter them, and configure which ones should be enabled across the platform.', 'wizard-ai'); ?></p>

            <div style="background: #fff; padding: 15px; border: 1px solid #ccc; margin-bottom: 20px; display: flex; gap: 20px; align-items: center;">
                <label>
                    <input type="checkbox" id="wai-cron-enabled" <?php echo $cron_enabled ? 'checked' : ''; ?>>
                    <?php esc_html_e('Enable automatic daily update of models', 'wizard-ai'); ?>
                </label>

                <button id="wai-trigger-update" class="button button-secondary"><?php esc_html_e('Force Update Models Now', 'wizard-ai'); ?></button>
                <button id="wai-save-settings" class="button button-primary"><?php esc_html_e('Save Settings', 'wizard-ai'); ?></button>
                <span id="wai-settings-spinner" class="spinner"></span>
            </div>

            <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" id="wai-models-search" placeholder="<?php esc_attr_e('Search models by name...', 'wizard-ai'); ?>" style="width: 100%; max-width: 300px; padding: 6px 12px;">
                
                <div id="wai-provider-filter" class="wai-dropdown-check-list">
                    <span class="anchor"><?php esc_html_e('Select Providers', 'wizard-ai'); ?></span>
                    <ul class="items">
                        <?php foreach ($providers as $prov) : ?>
                            <li><label><input type="checkbox" value="<?php echo esc_attr(strtolower($prov)); ?>" /> <span class="label-text" data-label="<?php echo esc_attr($prov); ?>"><?php echo esc_html($prov); ?></span></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div id="wai-family-filter" class="wai-dropdown-check-list">
                    <span class="anchor"><?php esc_html_e('Select Families', 'wizard-ai'); ?></span>
                    <ul class="items">
                        <?php foreach ($families as $fam) : ?>
                            <li><label><input type="checkbox" value="<?php echo esc_attr(strtolower($fam)); ?>" /> <span class="label-text" data-label="<?php echo esc_attr($fam); ?>"><?php echo esc_html($fam); ?></span></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div id="wai-capability-filter" class="wai-dropdown-check-list">
                    <span class="anchor"><?php esc_html_e('Select Capabilities', 'wizard-ai'); ?></span>
                    <ul class="items">
                        <?php foreach ($all_capabilities as $cap) : ?>
                            <?php $label = $capability_labels[$cap] ?? ucfirst(str_replace('_', ' ', $cap)); ?>
                            <li><label><input type="checkbox" value="<?php echo esc_attr(strtolower($cap)); ?>" /> <span class="label-text" data-label="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></span></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <select id="wai-status-filter" style="padding: 6px 12px;">
                    <option value="" data-label="<?php esc_attr_e('All Statuses', 'wizard-ai'); ?>"><?php esc_html_e('All Statuses', 'wizard-ai'); ?></option>
                    <option value="1" data-label="<?php esc_attr_e('Enabled', 'wizard-ai'); ?>"><?php esc_html_e('Enabled', 'wizard-ai'); ?></option>
                    <option value="0" data-label="<?php esc_attr_e('Disabled', 'wizard-ai'); ?>"><?php esc_html_e('Disabled', 'wizard-ai'); ?></option>
                </select>
            </div>
            
            <p style="margin-bottom: 10px;"><strong><?php esc_html_e('Total Models:', 'wizard-ai'); ?></strong> <span id="wai-total-models-count"><?php echo count($models_data); ?></span> / <?php echo count($models_data); ?></p>
            
            <table class="wp-list-table widefat fixed striped" id="wai-models-table">
                <thead>
                    <tr>
                        <th style="width: 5%;"><input type="checkbox" id="wai-models-select-all" checked></th>
                        <th style="width: 25%;"><?php esc_html_e('Name', 'wizard-ai'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Provider', 'wizard-ai'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Family', 'wizard-ai'); ?></th>
                        <th style="width: 40%;"><?php esc_html_e('Capabilities', 'wizard-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($models_data)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No models found.', 'wizard-ai'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($models_data as $m) : ?>
                            <tr class="wai-model-row">
                                <td>
                                    <input type="checkbox" class="wai-model-checkbox" value="<?php echo esc_attr($m['uid']); ?>" <?php echo $m['is_enabled'] ? 'checked' : ''; ?>>
                                </td>
                                <td class="wai-model-name" data-search="<?php echo esc_attr(strtolower($m['name'] . ' ' . $m['id'])); ?>">
                                    <strong><?php echo esc_html($m['name']); ?></strong><br>
                                    <small style="color:#666;"><?php echo esc_html($m['id']); ?></small>
                                </td>
                                <td class="wai-model-provider" data-filter="<?php echo esc_attr(strtolower($m['provider'])); ?>">
                                    <?php echo esc_html($m['provider']); ?>
                                </td>
                                <td class="wai-model-family" data-filter="<?php echo esc_attr(strtolower($m['family'])); ?>">
                                    <?php echo esc_html($m['family']); ?>
                                </td>
                                <td class="wai-model-capabilities" data-filter="<?php echo esc_attr(strtolower(implode(',', $m['capabilities']))); ?>">
                                    <small><?php echo esc_html(implode(', ', $m['capabilities'])); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
