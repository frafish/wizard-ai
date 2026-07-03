const { parse } = wp.blocks;
const block = parse('<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->')[0];
console.log(block.isValid);
