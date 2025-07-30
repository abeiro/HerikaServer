<!-- Javascript stuff to bring up a json editor for the metadata/override -->
 
<script>
let jsonEditor ;
function consolidation() {
    const content = jsonEditor.get()
    try {
        document.forms[0].metadata.value=JSON.stringify(content.json, null, 0)
    } catch (idontcare) {}
    
    if (document.forms[0].metadata.value=='')  {
        return confirm("Metadata is empty. You sure?");
    }

    
    return true;
}
</script>

<script type="module">
    import { createJSONEditor } from 'https://cdn.jsdelivr.net/npm/vanilla-jsoneditor/standalone.js'
    document.addEventListener("DOMContentLoaded", function() {
        let content = {
            text: undefined,
            json: <?php echo (!empty($editItem["metadata"])?$editItem["metadata"]:"{}") ?>
            
        }


        jsonEditor = createJSONEditor({
        target: document.getElementById('metadata'),
        props: {
            content,
            onChange: (updatedContent, previousContent, { contentErrors, patchResult }) => {
            // content is an object { json: JSONData } | { text: string }
            console.log('onChange', { updatedContent, previousContent, contentErrors, patchResult })
            content = updatedContent
            }
        }
        })
        console.log("javascript init done");

    });

</script>