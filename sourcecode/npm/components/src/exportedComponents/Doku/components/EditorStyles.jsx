import styled from 'styled-components';

export default styled.div`
    font-size: ${(props) => props.theme.rem(1)};
    position: relative;
    margin-top: ${(props) => (props.isEditing ? 60 : 0)}px;

    & h1 {
        font-weight: bold;
        font-size: ${(props) => props.theme.rem(2.3)};
    }

    h2 {
        margin: 25px 0 0 0;
    }

    h2 + div.paragraph {
        margin-top: 5px;
    }

    .DraftEditor-editorContainer,
    .DraftEditor-root,
    .public-DraftEditor-content {
        height: inherit;
        text-align: initial;
    }
    .public-DraftEditor-content[contenteditable='true'] {
        -webkit-user-modify: read-write-plaintext-only;
    }
    .DraftEditor-root {
        position: relative;
    }
    .DraftEditor-editorContainer {
        background-color: rgba(255, 255, 255, 0);
        border-left: 0.1px solid transparent;
        position: relative;
        z-index: 0;
    }
    .public-DraftEditor-block {
        position: relative;
    }
    .DraftEditor-alignLeft .public-DraftStyleDefault-block {
        text-align: left;
    }
    .DraftEditor-alignLeft .public-DraftEditorPlaceholder-root {
        left: 0;
        text-align: left;
    }
    .DraftEditor-alignCenter .public-DraftStyleDefault-block {
        text-align: center;
    }
    .DraftEditor-alignCenter .public-DraftEditorPlaceholder-root {
        margin: 0 auto;
        text-align: center;
        width: 100%;
    }
    .DraftEditor-alignRight .public-DraftStyleDefault-block {
        text-align: right;
    }
    .DraftEditor-alignRight .public-DraftEditorPlaceholder-root {
        right: 0;
        text-align: right;
    }
    .public-DraftEditorPlaceholder-root {
        color: #9197a3;
        position: absolute;
        z-index: 1;
    }
    .public-DraftEditorPlaceholder-hasFocus {
        color: #bdc1c9;
    }
    .DraftEditorPlaceholder-hidden {
        display: none;
    }
    .public-DraftStyleDefault-block {
        position: relative;
        white-space: pre-wrap;
    }
    .public-DraftStyleDefault-ltr {
        direction: ltr;
        text-align: left;
    }
    .public-DraftStyleDefault-rtl {
        direction: rtl;
        text-align: right;
    }
    .public-DraftStyleDefault-listLTR {
        direction: ltr;
    }
    .public-DraftStyleDefault-listRTL {
        direction: rtl;
    }
    .public-DraftStyleDefault-ol,
    .public-DraftStyleDefault-ul {
        margin: 16px 0;
        padding: 0;
    }
    .public-DraftStyleDefault-depth0.public-DraftStyleDefault-listLTR {
        margin-left: 1.5em;
    }
    .public-DraftStyleDefault-depth0.public-DraftStyleDefault-listRTL {
        margin-right: 1.5em;
    }
    .public-DraftStyleDefault-depth1.public-DraftStyleDefault-listLTR {
        margin-left: 3em;
    }
    .public-DraftStyleDefault-depth1.public-DraftStyleDefault-listRTL {
        margin-right: 3em;
    }
    .public-DraftStyleDefault-depth2.public-DraftStyleDefault-listLTR {
        margin-left: 4.5em;
    }
    .public-DraftStyleDefault-depth2.public-DraftStyleDefault-listRTL {
        margin-right: 4.5em;
    }
    .public-DraftStyleDefault-depth3.public-DraftStyleDefault-listLTR {
        margin-left: 6em;
    }
    .public-DraftStyleDefault-depth3.public-DraftStyleDefault-listRTL {
        margin-right: 6em;
    }
    .public-DraftStyleDefault-depth4.public-DraftStyleDefault-listLTR {
        margin-left: 7.5em;
    }
    .public-DraftStyleDefault-depth4.public-DraftStyleDefault-listRTL {
        margin-right: 7.5em;
    }
    .public-DraftStyleDefault-unorderedListItem {
        list-style-type: square;
        position: relative;
    }
    .public-DraftStyleDefault-unorderedListItem.public-DraftStyleDefault-depth0 {
        list-style-type: disc;
    }
    .public-DraftStyleDefault-unorderedListItem.public-DraftStyleDefault-depth1 {
        list-style-type: circle;
    }
    .public-DraftStyleDefault-orderedListItem {
        list-style-type: none;
        position: relative;
    }
    .public-DraftStyleDefault-orderedListItem.public-DraftStyleDefault-listLTR:before {
        left: -36px;
        position: absolute;
        text-align: right;
        width: 30px;
    }
    .public-DraftStyleDefault-orderedListItem.public-DraftStyleDefault-listRTL:before {
        position: absolute;
        right: -36px;
        text-align: left;
        width: 30px;
    }
    .public-DraftStyleDefault-orderedListItem:before {
        content: counter(ol0) '. ';
        counter-increment: ol0;
    }
    .public-DraftStyleDefault-orderedListItem.public-DraftStyleDefault-depth1:before {
        content: counter(ol1, lower-alpha) '. ';
        counter-increment: ol1;
    }
    .public-DraftStyleDefault-orderedListItem.public-DraftStyleDefault-depth2:before {
        content: counter(ol2, lower-roman) '. ';
        counter-increment: ol2;
    }
    .public-DraftStyleDefault-orderedListItem.public-DraftStyleDefault-depth3:before {
        content: counter(ol3) '. ';
        counter-increment: ol3;
    }
    .public-DraftStyleDefault-orderedListItem.public-DraftStyleDefault-depth4:before {
        content: counter(ol4, lower-alpha) '. ';
        counter-increment: ol4;
    }
    .public-DraftStyleDefault-depth0.public-DraftStyleDefault-reset {
        counter-reset: ol0;
    }
    .public-DraftStyleDefault-depth1.public-DraftStyleDefault-reset {
        counter-reset: ol1;
    }
    .public-DraftStyleDefault-depth2.public-DraftStyleDefault-reset {
        counter-reset: ol2;
    }
    .public-DraftStyleDefault-depth3.public-DraftStyleDefault-reset {
        counter-reset: ol3;
    }
    .public-DraftStyleDefault-depth4.public-DraftStyleDefault-reset {
        counter-reset: ol4;
    }
`;